//JavaScript to handle form submission based on filter selection
document.addEventListener("DOMContentLoaded", function() {
    var filterButton = document.getElementById('filter-button');
    if (filterButton) {
        filterButton.addEventListener('click', function(event) {
            event.preventDefault();

            var userFilter = document.getElementById('user-filter').value;
            var priceDifferenceFilter = document.getElementById('price-difference-filter').value;

            var url = window.location.href.split('?')[0];
            url += '?page=price-change-history';

            if (userFilter !== 'all') {
                url += '&user=' + userFilter;
            }

            if (priceDifferenceFilter !== 'all') {
                url += '&price_difference=' + priceDifferenceFilter;
            }

            window.location.href = url;
        });
    }
});
