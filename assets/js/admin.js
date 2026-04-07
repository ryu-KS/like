jQuery(function ($) {
  var mediaFrame = null;
  var currentTarget = null;

  $(document).on("click", ".taro-fortune-pick-media", function (event) {
    event.preventDefault();
    currentTarget = $(this).data("target");
    if (!currentTarget) return;

    if (!mediaFrame) {
      mediaFrame = wp.media({
        title: "Select image",
        button: { text: "Use this image" },
        multiple: false,
        library: { type: "image" }
      });

      mediaFrame.on("select", function () {
        var attachment = mediaFrame.state().get("selection").first().toJSON();
        $(currentTarget).val(attachment.url);
      });
    }

    mediaFrame.open();
  });

  $(document).on("click", ".taro-fortune-copy-shortcode", function () {
    var shortcode = $(this).data("shortcode");
    if (!shortcode) return;
    navigator.clipboard.writeText(shortcode);
    var button = $(this);
    button.text("Copied");
    setTimeout(function () { button.text("Copy"); }, 1200);
  });

  $(document).on("click", ".taro-admin-tab", function (event) {
    event.preventDefault();
    var tab = $(this).data("tab");
    if (!tab) return;
    $(".taro-admin-tab").removeClass("is-active");
    $(this).addClass("is-active");
    $(".taro-tab-panel").removeClass("is-active");
    $(".taro-tab-panel[data-tab-panel='" + tab + "']").addClass("is-active");
  });

  function syncResultUrl() {
    var option = $("#yesorno-result-post option:selected");
    var url = option.data("url") || "";
    $("#yesorno-result-url").val(url);
  }

  $(document).on("change", "#yesorno-result-post", syncResultUrl);
  syncResultUrl();
});
