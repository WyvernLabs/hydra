package downloader

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"mime"
	"net"
	"net/http"
	"net/netip"
	"net/url"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"emperror.dev/errors"
	"github.com/apex/log"
	"github.com/google/uuid"

	"github.com/pterodactyl/wings/config"
	wnet "github.com/pterodactyl/wings/internal/network"
	"github.com/pterodactyl/wings/server"
)

var client *http.Client

func init() {
	dialer := &net.Dialer{
		LocalAddr: nil,
		Timeout:   time.Second * 30,
		// Check every address immediately before connecting to it, since the
		// destination is resolved again when dialing.
		ControlContext: checkConnection,
	}

	trnspt := http.DefaultTransport.(*http.Transport).Clone()
	// Always connect directly, so that every destination is checked.
	trnspt.Proxy = nil
	// Store the file exactly as it is served. Asking for a compressed response
	// would also hide the length of the file, which is needed to write it.
	trnspt.DisableCompression = true
	trnspt.DialContext = func(ctx context.Context, network, addr string) (net.Conn, error) {
		host, _, err := net.SplitHostPort(addr)
		if err != nil {
			return nil, errors.WithStack(err)
		}
		// Refuse the destination if any of its addresses are internal, or if it
		// cannot be resolved.
		ips, err := net.DefaultResolver.LookupNetIP(ctx, "ip", host)
		if err != nil || len(ips) == 0 {
			log.WithField("host", host).WithField("error", err).Debug("downloader: failed to resolve remote file host")
			return nil, errors.WithStack(ErrInvalidIPAddress)
		}
		for _, ip := range ips {
			if isBlocked(host, ip) {
				return nil, errors.WithStack(ErrInternalResolution)
			}
		}
		return dialer.DialContext(context.WithValue(ctx, dialHostKey{}, host), network, addr)
	}

	client = &http.Client{
		Timeout:   time.Hour * 2,
		Transport: trnspt,
		// Hydra: redirects are followed (many direct links, e.g. GitHub releases, redirect to a
		// storage host). This is safe because every connection, also one made for a redirect, is
		// checked against internal addresses in DialContext and checkConnection above. Only
		// http(s) is allowed and at most five redirects.
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 5 {
				return errors.New("downloader: too many redirects")
			}
			if req.URL.Scheme != "http" && req.URL.Scheme != "https" {
				return errors.New("downloader: redirect to an unsupported scheme")
			}
			return nil
		},
	}
}

// dialHostKey is the context key for the host name a connection is being made
// to, which is checked against the allowlist when the connection is made.
type dialHostKey struct{}

// checkConnection is called with the address a connection is about to be made
// to, and refuses to connect to any internal address.
func checkConnection(ctx context.Context, _ string, address string, _ syscall.RawConn) error {
	ipStr, _, err := net.SplitHostPort(address)
	if err != nil {
		return errors.WithStack(err)
	}
	ip, err := netip.ParseAddr(ipStr)
	if err != nil {
		return errors.WithStack(ErrInternalResolution)
	}
	host, _ := ctx.Value(dialHostKey{}).(string)
	if isBlocked(host, ip) {
		return errors.WithStack(ErrInternalResolution)
	}
	return nil
}

// isBlocked reports whether connecting to the address, resolved from the host,
// is refused because it is internal and not allowed by the operator.
func isBlocked(host string, ip netip.Addr) bool {
	return wnet.IsInternal(ip) && !wnet.IsAllowed(config.Get().Api.RemoteDownloadAllowlist, host, ip)
}

var instance = &Downloader{
	// Tracks all the active downloads.
	downloadCache: make(map[string]*Download),
	// Tracks all the downloads active for a given server instance. This is
	// primarily used to make things quicker and keep the code a little more
	// legible throughout here.
	serverCache: make(map[string][]string),
}

const (
	ErrInternalResolution = errors.Sentinel("downloader: destination resolves to internal network location")
	ErrInvalidIPAddress   = errors.Sentinel("downloader: invalid IP address")
	ErrDownloadFailed     = errors.Sentinel("downloader: download request failed")
)

type Counter struct {
	total   int
	onWrite func(total int)
}

func (c *Counter) Write(p []byte) (int, error) {
	n := len(p)
	c.total += n
	c.onWrite(c.total)
	return n, nil
}

type DownloadRequest struct {
	Directory string
	URL       *url.URL
	FileName  string
	UseHeader bool
	// User (Hydra) is the UUID of the user who started the download, so it
	// can be stopped when they lose access to the server.
	User string
}

type Download struct {
	Identifier string
	path       string
	mu         sync.RWMutex
	req        DownloadRequest
	server     *server.Server
	progress   float64
	cancelFunc *context.CancelFunc
}

// New starts a new tracked download which allows for cancellation later on by calling
// the Downloader.Cancel function.
func New(s *server.Server, r DownloadRequest) *Download {
	dl := Download{
		Identifier: uuid.Must(uuid.NewRandom()).String(),
		req:        r,
		server:     s,
	}
	instance.track(&dl)
	return &dl
}

// ByServer returns all the tracked downloads for a given server instance.
func ByServer(sid string) []*Download {
	instance.mu.Lock()
	defer instance.mu.Unlock()
	var downloads []*Download
	if v, ok := instance.serverCache[sid]; ok {
		for _, id := range v {
			if dl, ok := instance.downloadCache[id]; ok {
				downloads = append(downloads, dl)
			}
		}
	}
	return downloads
}

// ByID returns a single Download matching a given identifier. If no download is found
// the second argument in the response will be false.
func ByID(dlid string) *Download {
	return instance.find(dlid)
}

func (dl *Download) MarshalJSON() ([]byte, error) {
	return json.Marshal(struct {
		Identifier string
		Progress   float64
	}{
		Identifier: dl.Identifier,
		Progress:   dl.Progress(),
	})
}

// Execute executes a given download for the server and begins writing the file to the disk. Once
// completed the download will be removed from the cache.
func (dl *Download) Execute() error {
	ctx, cancel := context.WithTimeout(context.Background(), time.Hour*12)
	dl.cancelFunc = &cancel
	defer dl.Cancel()

	// At this point we have verified the destination is not within the local network, so we can
	// now make a request to that URL and pull down the file, saving it to the server's data
	// directory.
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, dl.req.URL.String(), nil)
	if err != nil {
		return errors.WrapIf(err, "downloader: failed to create request")
	}

	req.Header.Set("User-Agent", "Hydra Panel")
	res, err := client.Do(req)
	if err != nil {
		if IsDownloadError(err) {
			return err
		}
		return errors.WithStack(fmt.Errorf("%w: %w", ErrDownloadFailed, err))
	}
	defer res.Body.Close()
	if res.StatusCode != http.StatusOK {
		return errors.New("downloader: got bad response status from endpoint: " + res.Status)
	}

	if res.ContentLength < 1 {
		return errors.New("downloader: request is missing ContentLength")
	}

	if dl.req.UseHeader {
		if contentDisposition := res.Header.Get("Content-Disposition"); contentDisposition != "" {
			_, params, err := mime.ParseMediaType(contentDisposition)
			if err != nil {
				return errors.WrapIf(err, "downloader: invalid \"Content-Disposition\" header")
			}

			// Only use the name of the file, so that it is saved in the
			// directory that was asked for.
			if v, ok := params["filename"]; ok {
				if name := filepath.Base(filepath.Clean("/" + v)); name != "/" && name != "." {
					dl.path = name
				}
			}
		}
	}
	if dl.path == "" {
		if dl.req.FileName != "" {
			dl.path = dl.req.FileName
		} else {
			// Hydra: after a redirect the name comes from the final address.
			u := dl.req.URL
			if res.Request != nil && res.Request.URL != nil {
				u = res.Request.URL
			}
			parts := strings.Split(u.Path, "/")
			dl.path = parts[len(parts)-1]
		}
		if dl.path == "" || dl.path == "." || dl.path == ".." {
			dl.path = "download"
		}
	}

	p := dl.Path()
	if err := dl.server.Filesystem().IsIgnored(p); err != nil {
		return err
	}
	dl.server.Log().WithField("path", p).Debug("writing remote file to disk")

	// Write the file while tracking the progress, Write will check that the
	// size of the file won't exceed the disk limit.
	r := io.TeeReader(res.Body, dl.counter(res.ContentLength))
	if err := dl.server.Filesystem().Write(p, r, res.ContentLength, 0o644); err != nil {
		return errors.WrapIf(err, "downloader: failed to write file to server directory")
	}
	return nil
}

// Cancel cancels a running download and frees up the associated resources. If a file is being
// written a partial file will remain present on the disk.
func (dl *Download) Cancel() {
	if dl.cancelFunc != nil {
		(*dl.cancelFunc)()
	}
	instance.remove(dl.Identifier)
}

// User returns the UUID of the user who started the download, empty if the
// Panel did not send one.
func (dl *Download) User() string {
	return dl.req.User
}

// BelongsTo checks if the given download belongs to the provided server.
func (dl *Download) BelongsTo(s *server.Server) bool {
	return dl.server.ID() == s.ID()
}

// Progress returns the current progress of the download as a float value between 0 and 1 where
// 1 indicates that the download is completed.
func (dl *Download) Progress() float64 {
	dl.mu.RLock()
	defer dl.mu.RUnlock()
	return dl.progress
}

func (dl *Download) Path() string {
	return filepath.Join(dl.req.Directory, dl.path)
}

// Handles a write event by updating the progress completed percentage and firing off
// events to the server websocket as needed.
func (dl *Download) counter(contentLength int64) *Counter {
	onWrite := func(t int) {
		dl.mu.Lock()
		defer dl.mu.Unlock()
		dl.progress = float64(t) / float64(contentLength)
	}
	return &Counter{
		onWrite: onWrite,
	}
}

// Downloader represents a global downloader that keeps track of all currently processing downloads
// for the machine.
type Downloader struct {
	mu            sync.RWMutex
	downloadCache map[string]*Download
	serverCache   map[string][]string
}

// track tracks a download in the internal cache for this instance.
func (d *Downloader) track(dl *Download) {
	d.mu.Lock()
	defer d.mu.Unlock()
	sid := dl.server.ID()
	if _, ok := d.downloadCache[dl.Identifier]; !ok {
		d.downloadCache[dl.Identifier] = dl
		if _, ok := d.serverCache[sid]; !ok {
			d.serverCache[sid] = []string{}
		}
		d.serverCache[sid] = append(d.serverCache[sid], dl.Identifier)
	}
}

// find finds a given download entry using the provided ID and returns it.
func (d *Downloader) find(dlid string) *Download {
	d.mu.RLock()
	defer d.mu.RUnlock()
	if entry, ok := d.downloadCache[dlid]; ok {
		return entry
	}
	return nil
}

// remove removes the given download reference from the cache storing them. This also updates
// the slice of active downloads for a given server to not include this download.
func (d *Downloader) remove(dlID string) {
	d.mu.Lock()
	defer d.mu.Unlock()
	if _, ok := d.downloadCache[dlID]; !ok {
		return
	}
	sID := d.downloadCache[dlID].server.ID()
	delete(d.downloadCache, dlID)
	if tracked, ok := d.serverCache[sID]; ok {
		var out []string
		for _, k := range tracked {
			if k != dlID {
				out = append(out, k)
			}
		}
		d.serverCache[sID] = out
	}
}

func IsDownloadError(err error) bool {
	return errors.Is(err, ErrDownloadFailed) || errors.Is(err, ErrInvalidIPAddress) || errors.Is(err, ErrInternalResolution)
}

// Result (Hydra) is a download that has ended, kept for a few minutes so the
// Panel can show whether it worked even though it ran in the background.
type Result struct {
	Identifier string    `json:"identifier"`
	URL        string    `json:"url"`
	Path       string    `json:"path"`
	Success    bool      `json:"success"`
	Error      string    `json:"error,omitempty"`
	FinishedAt time.Time `json:"finished_at"`
}

const resultLifetime = 10 * time.Minute

var finished = struct {
	sync.Mutex
	m map[string][]Result
}{m: map[string][]Result{}}

// Finish records the end of a download for the server.
func Finish(dl *Download, err error) {
	r := Result{
		Identifier: dl.Identifier,
		URL:        dl.req.URL.Redacted(),
		Success:    err == nil,
		FinishedAt: time.Now(),
	}
	if dl.path != "" {
		r.Path = dl.Path()
	}
	if err != nil {
		r.Error = ErrorCode(err)
	}
	sid := dl.server.ID()
	finished.Lock()
	defer finished.Unlock()
	list := append(finished.m[sid], r)
	if len(list) > 20 {
		list = list[len(list)-20:]
	}
	finished.m[sid] = list
}

// Finished returns the downloads of the server that ended in the last minutes.
func Finished(sid string) []Result {
	finished.Lock()
	defer finished.Unlock()
	var keep []Result
	for _, r := range finished.m[sid] {
		if time.Since(r.FinishedAt) < resultLifetime {
			keep = append(keep, r)
		}
	}
	if len(keep) == 0 {
		delete(finished.m, sid)
		return []Result{}
	}
	finished.m[sid] = keep
	return keep
}

// ErrorCode turns the error of a download into a short code the Panel can
// translate; unknown errors keep their message.
func ErrorCode(err error) string {
	msg := err.Error()
	switch {
	case errors.Is(err, context.Canceled):
		return "cancelled"
	case errors.Is(err, ErrInternalResolution):
		return "internal"
	case errors.Is(err, ErrInvalidIPAddress):
		return "resolve"
	case strings.Contains(msg, "missing ContentLength"):
		return "no_length"
	case strings.Contains(msg, "bad response status"):
		return "status:" + strings.TrimSpace(msg[strings.LastIndex(msg, ":")+1:])
	case strings.Contains(msg, "too many redirects"):
		return "redirects"
	case strings.Contains(strings.ToLower(msg), "disk"):
		return "disk"
	case strings.Contains(msg, "deadline exceeded"), strings.Contains(msg, "timeout"):
		return "timeout"
	}
	return msg
}
