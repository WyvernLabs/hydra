{{-- Hydra: label of a ticket priority. Expects $priority. --}}
<span class="label {{ ['low' => 'label-default', 'normal' => 'label-primary', 'high' => 'label-warning', 'urgent' => 'label-danger'][$priority] ?? 'label-default' }}">{{ __("admin/tickets.priorities.$priority") }}</span>
