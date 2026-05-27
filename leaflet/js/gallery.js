$(function() {
    galleryNextPrev = function(donext) {
        let $current = $(".gallery-image.current");
        $current.removeClass("current");
        if (donext) $next = $current.next(".gallery-image");
        else $next = $current.prev(".gallery-image");
        if ($next.length == 0) {
            if (donext) $next = $(".gallery-image").first();
            else $next = $(".gallery-image").last();
        }
        $next.addClass("current");
    }
    
    $("body").on("click", ".gallery-controls .next", function() {
        galleryNextPrev(true);
    });
    $("body").on("click", ".gallery-controls .prev", function() {
        galleryNextPrev(false);
    });
    
});