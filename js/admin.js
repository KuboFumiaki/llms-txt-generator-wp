jQuery(document).ready(function($) {
    // ---- 投稿タイプの並び替え ----

    function updateOrder() {
        var order = [];
        $("#post-type-order-list .post-type-item").each(function() {
            var postType = $(this).data("post-type");
            if (postType) {
                order.push(postType);
            }
        });
        $("#post_type_order").val(order.join(","));
    }

    function updateButtons() {
        var totalItems = $("#post-type-order-list .post-type-item").length;
        $("#post-type-order-list .post-type-item").each(function(index) {
            var $item = $(this);
            var isFirst = (index === 0);
            var isLast = (index === totalItems - 1);

            $item.find(".move-up").prop("disabled", isFirst).css("opacity", isFirst ? "0.3" : "1");
            $item.find(".move-down").prop("disabled", isLast).css("opacity", isLast ? "0.3" : "1");
        });
    }

    $(document).on("click", ".move-up:not(:disabled)", function(e) {
        e.preventDefault();
        var $currentItem = $(this).closest(".post-type-item");
        var $prevItem = $currentItem.prev(".post-type-item");

        if ($prevItem.length > 0) {
            $currentItem.insertBefore($prevItem);
            updateOrder();
            updateButtons();
        }
    });

    $(document).on("click", ".move-down:not(:disabled)", function(e) {
        e.preventDefault();
        var $currentItem = $(this).closest(".post-type-item");
        var $nextItem = $currentItem.next(".post-type-item");

        if ($nextItem.length > 0) {
            $currentItem.insertAfter($nextItem);
            updateOrder();
            updateButtons();
        }
    });

    updateOrder();
    updateButtons();

    // ---- 固定ページの一括選択 ----

    $("#check-all-pages").click(function(e) {
        e.preventDefault();
        $('input[name="enabled_pages[]"]').prop('checked', true);
    });

    $("#uncheck-all-pages").click(function(e) {
        e.preventDefault();
        $('input[name="enabled_pages[]"]').prop('checked', false);
    });

    // ---- 固定ページの並び替え ----

    function updatePageOrder() {
        var order = [];
        $("#page-order-list .page-item").each(function() {
            var pageId = $(this).data("page-id");
            if (pageId) {
                order.push(pageId);
            }
        });
        $("#page_order").val(order.join(","));
    }

    function updatePageButtons() {
        var totalItems = $("#page-order-list .page-item").length;
        $("#page-order-list .page-item").each(function(index) {
            var $item = $(this);
            var isFirst = (index === 0);
            var isLast = (index === totalItems - 1);

            $item.find(".page-move-up").prop("disabled", isFirst).css("opacity", isFirst ? "0.3" : "1");
            $item.find(".page-move-down").prop("disabled", isLast).css("opacity", isLast ? "0.3" : "1");
        });
    }

    $(document).on("click", ".page-move-up:not(:disabled)", function(e) {
        e.preventDefault();
        var $currentItem = $(this).closest(".page-item");
        var $prevItem = $currentItem.prev(".page-item");

        if ($prevItem.length > 0) {
            $currentItem.insertBefore($prevItem);
            updatePageOrder();
            updatePageButtons();
        }
    });

    $(document).on("click", ".page-move-down:not(:disabled)", function(e) {
        e.preventDefault();
        var $currentItem = $(this).closest(".page-item");
        var $nextItem = $currentItem.next(".page-item");

        if ($nextItem.length > 0) {
            $currentItem.insertAfter($nextItem);
            updatePageOrder();
            updatePageButtons();
        }
    });

    updatePageOrder();
    updatePageButtons();
});
