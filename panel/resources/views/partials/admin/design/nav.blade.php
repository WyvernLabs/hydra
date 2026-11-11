{{--
    Hydra: marks a page as part of the designer. Instead of tabs it gets the
    designer sidebar (see layouts/admin and designer-sidebar). Expects $activeTab.
    "design::nav" stays defined (empty) for the pages that still yield it.
--}}
@section('hydra-designer', $activeTab)
@section('design::nav', '')
