{{-- Hydra: label of a ticket status. Expects $status. --}}
<span class="label {{ ['open' => 'label-warning', 'answered' => 'label-info', 'closed' => 'label-default'][$status] ?? 'label-default' }}">{{ __("admin/tickets.statuses.$status") }}</span>
