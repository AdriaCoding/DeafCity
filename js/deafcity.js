var thePlayer = null;
var iframe_string = '';

$(function() {
    
    createPlayer();
    
    $("body").on("click", "#next:not(.disabled)", function() {
        nextVideo();
    });
    $("body").on("click", "#prev:not(.disabled)", function() {
        prevVideo();
    });
    
    $("#thumbs").on("click", ".video .icon-play", function() {
        var $video = $(this).closest(".video");
        if ($video.hasClass("current")) {
            getPlayer().play();
        }
        else {
            playVideo($video);
        }
    });
    
    $("body").on("click", "#thumbs .video.current .icon-pause, #all-lists .selected .icon-pause", function() {
        getPlayer().pause();
    });
    
    // unused because #all-lists is hidden
    $("#all-lists").on("click", ".icon-play", function() {
        var $playlist = $(this).closest("li");
        if ($playlist.hasClass("selected")) {
            getPlayer().play();
        }
        else {
            playList($playlist);
        }
    });
    
    $("select.playlist-selector").on("change", function() {
        playList($(this).find("option:selected"));
    });
    
    $(window).on("scroll", function() {
        handleScroll();
    });
    $("#everything").on("scroll", function() {
        handleScroll();
    });
    $(window).on("resize", function() {
        handleScroll();
    });
    handleScroll();
    
    $("#contact form").on("submit", function() {
        grecaptcha.execute();
        return false;
    });
});

function getPlayer() {
    return thePlayer;
}

function createPlayer(videoElem) {
    
    if (!iframe_string) {
        iframe_string = $("#player iframe")[0].outerHTML;
    }
    
    if (videoElem) {
        destroyPlayer();
        //$("#player iframe").attr("src", "https://player.vimeo.com/video/"+$(videoElem).data("vimeoid")+"?texttrack="+$(videoElem).data("subtitles"));
        $("#current-video").html(
                iframe_string.replace(/src="[^"]/, "src=\"https://player.vimeo.com/video/"+$(videoElem).data("vimeoid")+"?texttrack="+$(videoElem).data("subtitles")+"\"")
        );
        let aspect = $(videoElem).data("aspect");
        if (aspect !== undefined) {
            console.log("Aspect ratio: " + aspect);
            $("#current-video iframe").attr("style", "aspect-ratio: " + aspect);
        }
    }
    thePlayer = new Vimeo.Player($("#current-video iframe")[0]);
    
    if (videoElem) {
        var player = thePlayer;
        player.ready().then(function() {
            player.play().then(function() {
                //player.enableTextTrack($video.data("subtitles"), "subtitles");
            });
        });    
    }
    
    initPlayer();
}

function initPlayer() {
    
    var player = thePlayer;
    
    player.on('ended', function(data) {
        try {
            nextVideo();
        } catch (e) {
            console.error(e);
        }
    });
    player.on('play', function() {
        try {
            $("#all-lists .selected").addClass("playing");
            $("#thumbs .playlist.selected .video.current").addClass("playing");
        } catch (e) {
            console.error(e);
        }
    });
    player.on('pause', function() {
        try {
            $(".playing").removeClass("playing");
        } catch (e) {
            console.error(e);
        }
    });
}

function destroyPlayer() {
    /*
    thePlayer.off('ended');
    thePlayer.off('play');
    thePlayer.off('pause');
    */
    thePlayer.destroy();
}

function nextVideo() {
    //console.log("nextVideo");
    var playlist = $("#player").data("playlist");
    //console.log("playlist: ",playlist);
    var $nextVideo = $("#thumbs-playlist-" + playlist + " .video.current").next();
    if ($nextVideo.length > 0) {
        playVideo($nextVideo);
        return true;
    }
    else {
        playListEnded();
        return false;
    }
}

function prevVideo() {
    var playlist = $("#player").data("playlist");
    var $nextVideo = $("#thumbs-playlist-" + playlist + " .video.current").prev();
    if ($nextVideo.length > 0) {
        playVideo($nextVideo);
        return true;
    }
    else {
        playListEnded();
        return false;
    }
}

function playVideo($video) {
    //console.log("playVideo", $video[0]);
    $("#thumbs .playlist .current").removeClass("current");
    $(".playing").removeClass("playing");
    $video.addClass("current");
    var videoID = $video.data("vimeoid");
    //console.log(videoID);
    createPlayer($video[0]);
    checkDisableButtons();
}

function playList($playlist) {
    //console.log("playList", $playlist[0]);
    $("#all-lists .selected, #thumbs .selected").removeClass("selected");
    $(".playing").removeClass("playing");
    
    var listid = "";
    if ($playlist.is("option")) {
        listid = $playlist.val();
    }
    else {
        listid = $playlist.attr("id").split("-").pop();
        $playlist.addClass("selected");
    }
    $("#player").data("playlist", listid);
    
    
    $("#thumbs-playlist-"+listid).addClass("selected");
    $("#title").text($playlist.find(".title").text()); // #title no longer exists
    playVideo($("#thumbs-playlist-"+listid+" .video").first());
    
}

function playListEnded() {
    getPlayer().pause();
}

function checkDisableButtons() {
    var $currentVideo = $("#thumbs .playlist.selected .video.current");
    $("#prev, #next").removeClass("disabled");
    if ($currentVideo.next(".video").length==0) $("#next").addClass("disabled");
    if ($currentVideo.prev(".video").length==0) $("#prev").addClass("disabled");
    
}

function handleScroll() {
    var found = false;
    $(".menu .navitem").removeClass("current-scroll").each(function() {
        var id = $(this).attr("href").substr(1);
        var elem = $("#"+id)[0]
        var rect = elem.getBoundingClientRect();
        var bottomrect = $("#about")[0].getBoundingClientRect();
        //console.log(id, rect);
        if (rect.top < 50 || (id=='about' && bottomrect.top + bottomrect.height - $(window).height() < 30)) {
            found = id;
            //if (id=='about') console.log("?!?", $(window).height(), rect.top, rect.height);
        }
    });
    //console.log(found);
    if (found) {
        //console.log("#menu ."+found+"-link");
        $(".menu ."+found+"-link").addClass("current-scroll");
    }
    
}

function submitContactForm(token) {
    console.log("submit token", token);
    var data = $("#contact form").serializeArray();
    data.push({name: "grecaptcha-token", value: token});
    console.log("submit data", data);
    
    $("#contact .error, #contact .confirm").addClass("hidden");
    
    $("#form button").prop("disabled", true);
    
    $.ajax({
        url: $("#contact form").attr("action"),
        data: data,
        method: 'POST',
        success: function(data) {
            console.log("Response: ", data);
            if (typeof(data)=="object" && data.ok==1) {
                $("#contact .confirm").removeClass("hidden");
                submitComplete();
            }
            else {
                if (typeof(data)=="object" && data.error!=undefined) submitError(data.error);
                else submitError('ERROR');
            }
        },
        error: function(jqXHR, textStatus, errorThrown ) {
            //console.log("Error submitting data", jqXHR, textStatus, errorThrown );
            var errorText = '';
            if (jqXHR.responseText != undefined) {
                var response = {};
                try {
                    response = JSON.parse(jqXHR.responseText);
                }
                catch (error) {
                    console.log(error);
                }
                if (response.error) errorText = response.error;
            }
            if (!errorText) errorText = 'ERROR';
            submitError(errorText);
            
        }
    });
    
    
    
}
function submitError(errorText) {
    $("#contact .error").removeClass("hidden");
    $("#contact .error").html(errorText);
    submitComplete();
}

function submitComplete() {
    $("#contact button").prop("disabled", false);
    grecaptcha.reset();
}
