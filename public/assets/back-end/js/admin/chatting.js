"use strict";

$(document).ready(function () {
    const $document = $(document);
    const pollingIntervalInMs = 4000;
    let activeChatPollingInterval = null;

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr("content");
    }

    function getChatBaseUrl() {
        return $("#chatting-post-url").data("url") || "";
    }

    function getCurrentActiveChat() {
        return $(".get-ajax-message-view.active").first();
    }

    function getCurrentUserId() {
        const hiddenId = $("#current-user-hidden-id").val();
        if (hiddenId !== undefined && hiddenId !== null && hiddenId !== "") {
            return hiddenId;
        }

        const $activeChat = getCurrentActiveChat();
        return $activeChat.length ? $activeChat.data("user-id") : "";
    }

    function getSelectedFilesCount() {
        const filesCount =
            typeof selectedFiles !== "undefined" && Array.isArray(selectedFiles)
                ? selectedFiles.length
                : 0;

        const imagesCount =
            typeof selectedImages !== "undefined" && Array.isArray(selectedImages)
                ? selectedImages.length
                : 0;

        return filesCount + imagesCount;
    }

    function clearSelectedUploads() {
        $(".image-array").empty();
        $(".file-array").empty();
        $(".input-uploaded-file").empty();

        const filesContainer = document.getElementById("selected-files-container");
        const mediaContainer = document.getElementById("selected-media-container");

        if (filesContainer) {
            filesContainer.innerHTML = "";
        }

        if (mediaContainer) {
            mediaContainer.innerHTML = "";
        }

        if (typeof selectedFiles !== "undefined") {
            selectedFiles = [];
        }

        if (typeof selectedImages !== "undefined") {
            selectedImages = [];
        }
    }

    function setAjaxHeaders() {
        $.ajaxSetup({
            headers: {
                "X-XSRF-TOKEN": getCsrfToken(),
            },
        });
    }

    function initializeTooltips() {
        $('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').tooltip();
    }

    function updateEmptyState() {
        let visibleUsers = $(".list_filter:visible").length;

        if (visibleUsers > 0) {
            $(".empty-state-for-chatting-msg").addClass("d-none").removeClass("d-flex");
        } else {
            $(".empty-state-for-chatting-msg").removeClass("d-none").addClass("d-flex");
        }
    }

    function filterChatList() {
        const value = ($("#myInput").val() || "").toLowerCase();

        $(".list_filter").each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });

        updateEmptyState();
    }

    function updateHeaderAndMessages(response, userId) {
        if (!response || !response.userData) {
            return;
        }

        $("#chatting-messages-section").html(response.chattingMessages || "");
        $("#profile_image").attr("src", response.userData.image || "");
        $("#profile_name").html(response.userData.name || "");
        $("#profile_phone").html(response.userData.phone || "");
        $("#current-user-hidden-id").val(userId);
        $(".user-details-route").attr("href", response.userData.detailsRoute || "#");

        namePdf();
        initializeTooltips();
        scrollChatToLatest();
    }

    function scrollChatToLatest() {
        const container = document.getElementById("chatting-messages-section");
        if (container) {
            container.scrollTop = 0;
        }
    }

    function loadChatMessages(userId, options = {}) {
        if (!userId) {
            return;
        }

        const actionURL = getChatBaseUrl() + encodeURIComponent(userId);

        setAjaxHeaders();

        $.ajax({
            url: actionURL,
            type: "GET",
            dataType: "json",
            cache: false,
            beforeSend: function () {
                if (options.showLoader !== false) {
                    $("#loading").fadeIn();
                }
            },
            success: function (response) {
                updateHeaderAndMessages(response, userId);

                if (options.keepActiveScrollIntoView !== false) {
                    const $activeChat = getCurrentActiveChat();
                    if ($activeChat.length) {
                        $activeChat[0].scrollIntoView({
                            behavior: "auto",
                            block: "nearest",
                            inline: "center",
                        });
                    }
                }
            },
            complete: function () {
                if (options.showLoader !== false) {
                    $("#loading").fadeOut();
                }
            },
        });
    }

    function startActiveChatPolling() {
        stopActiveChatPolling();

        activeChatPollingInterval = setInterval(function () {
            if (document.hidden) {
                return;
            }

            const userId = getCurrentUserId();
            if (!userId) {
                return;
            }

            loadChatMessages(userId, {
                showLoader: false,
                keepActiveScrollIntoView: false,
            });
        }, pollingIntervalInMs);
    }

    function stopActiveChatPolling() {
        if (activeChatPollingInterval) {
            clearInterval(activeChatPollingInterval);
            activeChatPollingInterval = null;
        }
    }

    function updateActiveChatPreview(messageText) {
        const $activeChat = getCurrentActiveChat();
        if (!$activeChat.length) {
            return;
        }

        const previewText = (messageText || "").trim() !== "" ? messageText : "Shared files";
        $activeChat.find(".line--limit-1").first().text(previewText);
    }

    $("#myInput").on("keyup keypress change", function () {
        filterChatList();
    });

    $("#chat-search-form").on("submit", function (e) {
        e.preventDefault();
    });

    $document.on("click", ".get-ajax-message-view", function (e) {
        e.preventDefault();

        const $this = $(this);
        const userId = $this.data("user-id");

        $(".get-ajax-message-view").removeClass("bg-soft-secondary active");
        $this.addClass("bg-soft-secondary active");

        $(".notify-alert-" + userId).remove();
        $("#count-unread-messages-" + userId).remove();

        $(".get-ajax-message-view").find(".chat_ib h5").addClass("font-weight-normal");
        $this.find(".chat_ib h5").removeClass("font-weight-normal");

        loadChatMessages(userId, {
            showLoader: true,
            keepActiveScrollIntoView: true,
        });

        startActiveChatPolling();
    });

    $document.on("submit", ".chatting-messages-ajax-form", function (event) {
        event.preventDefault();

        const form = this;
        const formData = new FormData(form);
        const totalFilesCount = getSelectedFilesCount();
        const currentMessageText = ($("#msgInputValue").val() || "").trim();

        setAjaxHeaders();

        $.ajax({
            type: "POST",
            url: getChatBaseUrl(),
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            xhr: function () {
                const xhr = new window.XMLHttpRequest();

                xhr.upload.addEventListener(
                    "progress",
                    function (evt) {
                        if (evt.lengthComputable && totalFilesCount > 0) {
                            const percentComplete = (evt.loaded / evt.total) * 100;

                            $(".circle-progress").show();
                            $(".circle-progress").find(".text").text(`Uploading ${totalFilesCount} files`);
                            $(".circle-progress")
                                .find("#bar")
                                .attr("stroke-dashoffset", 100 - percentComplete);
                        }
                    },
                    false
                );

                return xhr;
            },
            beforeSend: function () {
                $("#msgSendBtn").attr("disabled", true);
                $("#loading").fadeIn();
            },
            success: function (response) {
                $("#msgInputValue").val("");
                clearSelectedUploads();
                updateActiveChatPreview(currentMessageText);

                const userId = getCurrentUserId();

                if (userId) {
                    loadChatMessages(userId, {
                        showLoader: false,
                        keepActiveScrollIntoView: false,
                    });
                } else if (response.chattingMessages) {
                    $("#chatting-messages-section").html(response.chattingMessages);
                    namePdf();
                    initializeTooltips();
                    scrollChatToLatest();
                }

                setTimeout(() => {
                    $(".circle-progress").find(".text").text(`Uploaded ${totalFilesCount} files`);
                    $(".circle-progress").hide();
                }, 1000);

                startActiveChatPolling();
            },
            complete: function () {
                $("#loading").fadeOut();
                $(".circle-progress").hide();
                $("#msgSendBtn").removeAttr("disabled");
                initializeTooltips();

                setTimeout(() => {
                    $(".circle-progress").find(".text").text(`Uploaded ${totalFilesCount} files`);
                    $(".circle-progress").hide();
                }, 1000);
            },
            error: function (error) {
                if (error.status === 413) {
                    toastMagic.warning($("#message-media-error").data("text"));
                } else {
                    try {
                        const errorData = JSON.parse(error.responseText);
                        toastMagic.warning(errorData.message);
                    } catch (e) {
                        toastMagic.error($("#message-media-error").data("text"));
                    }
                }

                setTimeout(() => {
                    $(".circle-progress").find(".text").text(`Uploaded ${totalFilesCount} files`);
                    $(".circle-progress").hide();
                }, 1000);
            },
        });
    });

    $document.on("click", '[data-target^="#imgViewModal"], [data-bs-target^="#imgViewModal"]', function () {
        const modalId = $(this).attr("data-bs-target") || $(this).attr("data-target");
        if (!modalId) {
            return;
        }

        const $modal = $(modalId);
        const $carousel = $modal.find(".imgView-slider");
        const slideCount = $modal.find(".imgView-item").length;

        if ($carousel.hasClass("owl-loaded")) {
            $carousel.trigger("destroy.owl.carousel");
            $carousel.removeClass("owl-loaded");
            $carousel.find(".owl-stage-outer").children().unwrap();
        }

        const imgView = $carousel.owlCarousel({
            items: 1,
            loop: false,
            margin: 0,
            nav: false,
            dots: false,
            mouseDrag: slideCount > 1,
            touchDrag: slideCount > 1,
            autoplay: false,
            smartSpeed: 500,
            onChanged: function (event) {
                const currentIndex = event.item.index;

                if (currentIndex === 0) {
                    $modal.find(".imgView-owl-prev").prop("disabled", true).addClass("disabled");
                } else {
                    $modal.find(".imgView-owl-prev").prop("disabled", false).removeClass("disabled");
                }

                if (currentIndex === slideCount - 1) {
                    $modal.find(".imgView-owl-next").prop("disabled", true).addClass("disabled");
                } else {
                    $modal.find(".imgView-owl-next").prop("disabled", false).removeClass("disabled");
                }
            },
        });

        if (slideCount <= 1) {
            $modal.find(".imgView-owl-prev, .imgView-owl-next").hide();
        } else {
            $modal.find(".imgView-owl-prev, .imgView-owl-next").show();

            $modal.find(".imgView-owl-prev").off("click.chatting").on("click.chatting", function () {
                imgView.trigger("prev.owl.carousel");
            });

            $modal.find(".imgView-owl-next").off("click.chatting").on("click.chatting", function () {
                imgView.trigger("next.owl.carousel");
            });
        }

        const index = $(this).data("index");
        if (slideCount > 1 && index !== undefined) {
            imgView.trigger("to.owl.carousel", [index, 0]);
        }

        $modal.find(".imgView-item").each(function () {
            const imgSrc = $(this).find("img").attr("src");
            if (imgSrc) {
                const imgTitle = imgSrc.split("/").pop();
                $(this).find(".img-title").text(imgTitle);
            }
        });
    });

    $document.on("click", ".modal_video-play-btn", function () {
        const videoElement = $(this).siblings("video")[0];

        if (videoElement) {
            videoElement.controls = true;
            videoElement.play();
        }

        $(this).remove();
    });

    $document.on("play", "video", function () {
        $(this).siblings(".modal_video-play-btn").remove();
    });

    $document.on("pause", "video", function () {
        const videoElement = this;

        if (!$(videoElement).siblings(".modal_video-play-btn").length) {
            const iconSrc = $("#get-video-preview-icon").data("icon");

            const playButton = $("<button>", {
                type: "button",
                class: "btn video-play-btn modal_video-play-btn p-1",
                html: `<img src="${iconSrc}" alt="Play">`,
            });

            playButton.insertAfter(videoElement);

            playButton.on("click", function () {
                videoElement.controls = true;
                videoElement.play();
                $(this).remove();
            });
        }
    });

    $document.on("click", ".zip-download", function (event) {
        event.preventDefault();

        const zipWrapper = $(this).closest(".zip-wrapper").find(".zip-images");
        if (!zipWrapper.length) {
            return;
        }

        const zip = new JSZip();
        const zipFolder = zip.folder("files");
        const images = zipWrapper.find("img");
        const videos = zipWrapper.find("video");

        if (images.length === 0 && videos.length === 0) {
            return;
        }

        const mediaPromises = [];

        images.each((index, img) => {
            const imgUrl = $(img).attr("src");
            const filename = `image_${index + 1}.png`;

            mediaPromises.push(
                fetch(imgUrl)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(`Failed to fetch image: ${imgUrl}`);
                        }
                        return response.blob();
                    })
                    .then((blob) => zipFolder.file(filename, blob))
            );
        });

        videos.each((index, video) => {
            const videoUrl = $(video).find("source").first().attr("src");
            const filename = `video_${index + 1}.mp4`;

            if (videoUrl) {
                mediaPromises.push(
                    fetch(videoUrl)
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error(`Failed to fetch video: ${videoUrl}`);
                            }
                            return response.blob();
                        })
                        .then((blob) => zipFolder.file(filename, blob))
                );
            }
        });

        Promise.all(mediaPromises)
            .then(() => zip.generateAsync({ type: "blob" }))
            .then((content) => saveAs(content, "files.zip"))
            .catch(() => {
            });
    });

    function namePdf() {
        $(".pdf-file-name").each(function () {
            const text = $(this).text().trim();
            const lastDotIndex = text.lastIndexOf(".");

            if (lastDotIndex !== -1) {
                const namePart = text.slice(0, lastDotIndex);
                const extension = text.slice(lastDotIndex);
                const maxLength = 20;

                if (namePart.length > maxLength) {
                    const truncatedName =
                        namePart.slice(0, maxLength - extension.length - 3) + "..." + extension;
                    $(this).text(truncatedName);
                } else {
                    $(this).text(text);
                }
            }
        });
    }

    $document.on("show.bs.modal", ".imgViewModal", function () {
        $('[data-toggle="tooltip"], [data-bs-toggle="tooltip"]').tooltip("dispose");
    });

    $document.on("hidden.bs.modal", ".imgViewModal", function () {
        initializeTooltips();
    });

    filterChatList();
    namePdf();
    initializeTooltips();

    if (getCurrentUserId()) {
        startActiveChatPolling();
    }

    $(window).on("beforeunload", function () {
        stopActiveChatPolling();
    });
});
