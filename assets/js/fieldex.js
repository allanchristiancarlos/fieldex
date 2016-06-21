(function(w, $){
    var toggleButton     = document.getElementById('js-fieldex-toggle-filter');
    var filterForm       = document.getElementById('js-fieldex-form');
    var postsForm        = document.getElementById('posts-filter');
    var navtop           = document.querySelector('.fieldex .tablenav.top');
    var navtopMainHeight = navtop.clientHeight;
    var resetButton      = document.getElementById('js-fieldex-reset-button');

    resetButton.addEventListener('click', function(e) {
        e.preventDefault();
        postsForm.reset();

        setTimeout(function() {
            $('#js-fieldex-form').find('select, input, textarea').attr('disabled', 'disabled');
            $('#js-fieldex-form').find('select, input, textarea').prop('disabled', 'disabled');
            postsForm.submit();
        }, 90);
    });

    toggleButton.addEventListener('click', function(e) {
        e.preventDefault();

        if (filterForm.style.display == 'none' || filterForm.style.display.toString().length == 0) {
            toggleButton.innerHTML   = 'Hide Filters';
            filterForm.style.display = 'block';
            navtop.style.height      = filterForm.clientHeight + navtopMainHeight + 'px';
        } else {
            toggleButton.innerHTML   = 'Advanced Filters';
            filterForm.style.display = 'none';
            navtop.style.height      = navtopMainHeight + 'px';
        }
    });
    
})(window, jQuery);