{{-- Everything that changes as orders move — served alone by kitchen.board for the 5-second refresh;
     resources/js/modules/kitchen.js swaps each [data-k-part] into the page. --}}
@include('kitchen.partials.stats')
@include('kitchen.partials.columns')
@include('kitchen.partials.recent')
