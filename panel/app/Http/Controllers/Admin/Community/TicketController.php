<?php

namespace Pterodactyl\Http\Controllers\Admin\Community;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\TicketAttachment;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Addons\TicketsAddon;
use Pterodactyl\Services\Hydra\Tickets\TicketService;

/**
 * Hydra: the team answers support tickets in the admin area under
 * Community > Tickets (tickets addon).
 */
class TicketController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private TicketService $service,
        private TicketsAddon $addon,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->input('status', 'active');
        $search = trim((string) $request->input('search'));

        $tickets = Ticket::query()
            ->with(['user', 'server', 'assignee'])
            ->when($status === 'active', fn ($query) => $query->unclosed())
            ->when(in_array($status, Ticket::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($request->input('assigned') === 'me', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($request->input('assigned') === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->when($request->filled('language'), fn ($query) => $query->where('language', $request->input('language')))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('subject', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn ($user) => $user->where('username', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'));
                    if (ctype_digit(ltrim($search, '#'))) {
                        $query->orWhere('id', (int) ltrim($search, '#'));
                    }
                });
            })
            // Waiting for the team first, then the most urgent, then the oldest answer.
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('last_reply_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'status' => $status,
            'addon' => $this->addon,
            'counts' => Ticket::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function view(Ticket $ticket): View
    {
        $ticket->load(['user', 'server', 'assignee', 'messages.user']);

        return view('admin.tickets.view', [
            'ticket' => $ticket,
            'addon' => $this->addon,
            'html' => $ticket->messages->mapWithKeys(fn ($message) => [$message->id => $this->service->html($message)]),
            'team' => $this->team(),
            'uploadLimit' => $this->addon->uploadLimit(),
            'otherTickets' => Ticket::query()->where('user_id', $ticket->user_id)->where('id', '!=', $ticket->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:20000',
            'internal' => 'nullable|boolean',
            'close' => 'nullable|boolean',
        ]);

        $internal = $request->boolean('internal');
        $this->service->reply($ticket, $request->user(), $data['body'], true, $internal);

        if ($request->boolean('close') && $request->user()->hasAdminPermission('tickets.manage')) {
            $this->service->close($ticket->refresh());
        }

        $this->alert->success(__($internal ? 'admin/tickets.alerts.note_added' : 'admin/tickets.alerts.replied'))->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->team()->modelKeys())],
        ]);

        if ($data['status'] === Ticket::STATUS_CLOSED && !$ticket->isClosed()) {
            $this->service->close($ticket);
        } elseif ($data['status'] !== Ticket::STATUS_CLOSED && $ticket->isClosed()) {
            $this->service->reopen($ticket);
        }

        $ticket->forceFill([
            'status' => $data['status'],
            'priority' => $data['priority'],
            'assigned_to' => $data['assigned_to'] ?? null,
        ])->save();

        $this->alert->success(__('admin/tickets.alerts.updated'))->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    public function delete(Ticket $ticket): RedirectResponse
    {
        $this->service->delete($ticket);
        $this->alert->success(__('admin/tickets.alerts.deleted', ['id' => $ticket->id]))->flash();

        return redirect()->route('admin.tickets');
    }

    /**
     * Uploads an image for a message of the team (JSON, used by the editor).
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

    /**
     * Team members a ticket can be assigned to: everyone who may answer tickets.
     */
    private function team(): Collection
    {
        return User::query()
            ->where('root_admin', true)
            ->orWhereHas('adminRoles')
            ->orderBy('username')
            ->get()
            ->filter(fn (User $user) => $user->hasAdminPermission('tickets.reply'))
            ->values();
    }
}
