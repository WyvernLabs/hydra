<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Services\Hydra\Addons\TicketsAddon;
use Pterodactyl\Services\Hydra\Tickets\TicketService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: support tickets of the logged in user (tickets addon). Internal
 * notes of the team are never sent here.
 */
class TicketController extends ClientApiController
{
    public function __construct(private TicketService $service, private TicketsAddon $addon)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        $tickets = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->when($request->input('status') === 'closed', fn ($query) => $query->where('status', Ticket::STATUS_CLOSED))
            ->when($request->input('status') !== 'closed' && $request->input('status') !== 'all', fn ($query) => $query->unclosed())
            ->with('server')
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->paginate(20);

        return [
            'data' => $tickets->getCollection()->map(fn (Ticket $ticket) => $this->ticket($ticket))->all(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $categories = array_column($this->addon->categories(), 'id');
        $data = $request->validate([
            'subject' => 'required|string|between:3,191',
            'body' => 'required|string|max:20000',
            'language' => ['required', 'string', Rule::in($this->addon->languages())],
            'category' => count($categories) > 0 ? ['required', 'string', Rule::in($categories)] : 'prohibited',
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'server' => 'nullable|string',
        ]);

        $server = null;
        if (!empty($data['server'])) {
            $server = $request->user()->accessibleServers()
                ->where(fn ($query) => $query->where('servers.uuid', $data['server'])->orWhere('servers.uuidShort', $data['server']))
                ->first();
            if (!$server instanceof Server) {
                throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
            }
        }

        $ticket = $this->service->create($request->user(), $data, $server);

        return new JsonResponse($this->ticket($ticket->refresh()), Response::HTTP_CREATED);
    }

    public function view(Request $request, int $ticket): array
    {
        $ticket = $this->find($request, $ticket);
        $ticket->load(['server', 'messages' => fn ($query) => $query->where('internal', false)->with('user')]);

        return $this->ticket($ticket) + [
            'messages' => $ticket->messages->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'html' => $this->service->html($message),
                'staff' => $message->staff,
                'mine' => $message->user_id === $request->user()->id,
                'author' => $message->user ? [
                    'username' => $message->user->username,
                    'avatar_url' => $message->user->avatarUrl(),
                ] : null,
                'created_at' => $message->created_at->toAtomString(),
            ])->all(),
        ];
    }

    /**
     * Answering a closed ticket opens it again.
     */
    public function reply(Request $request, int $ticket): JsonResponse
    {
        $ticket = $this->find($request, $ticket);
        $data = $request->validate(['body' => 'required|string|max:20000']);

        $this->service->reply($ticket, $request->user(), $data['body'], false);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $this->service->close($this->find($request, $ticket));

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Uploads an image, which can then be shown in a message.
     */
    public function upload(Request $request): array
    {
        $request->validate([
            'image' => [
                'required',
                'file',
                'mimetypes:' . implode(',', TicketAttachment::MIMES),
                'max:' . ($this->addon->uploadLimit() * 1024),
            ],
        ]);

        $file = $this->service->upload($request->user(), $request->file('image'));

        return ['uuid' => $file->uuid, 'url' => $file->url(), 'name' => $file->name];
    }

    public function preview(Request $request): array
    {
        $data = $request->validate(['body' => 'nullable|string|max:20000']);

        return ['html' => $this->service->render($data['body'] ?? '')];
    }

    private function find(Request $request, int $id): Ticket
    {
        return Ticket::query()->where('id', $id)->where('user_id', $request->user()->id)->first()
            ?? throw new NotFoundHttpException();
    }

    private function ticket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            // Hydra: the category in the language of the ticket.
            'category' => $this->addon->categoryName($ticket->category, $ticket->language),
            'language' => $ticket->language,
            'language_name' => $ticket->language ? TicketsAddon::languageName($ticket->language) : null,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'server' => $ticket->server ? ['identifier' => $ticket->server->uuidShort, 'name' => $ticket->server->name] : null,
            'last_reply_at' => $ticket->last_reply_at?->toAtomString(),
            'created_at' => $ticket->created_at->toAtomString(),
        ];
    }
}
