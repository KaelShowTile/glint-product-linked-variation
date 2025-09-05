jQuery(function($) {

    $('.glint-product-search').select2({
        ajax: {
            url: glintLinkedVars.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    action: 'glint_linked_variation_search_products',
                    nonce: glintLinkedVars.nonce
                };
            },
            processResults: function(data) {
                return {
                    results: data.results
                };
            }
        },
        minimumInputLength: 3,
        placeholder: 'Search for products',
        allowClear: true
    });
});