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

    // チェックを外した投稿タイプを順序リスト上で薄く表示する
    function updatePostTypeDim() {
        $('input[name="enabled_post_types[]"]').each(function() {
            var postType = $(this).val();
            var checked = $(this).prop("checked");
            $("#post-type-order-list .post-type-item").filter(function() {
                return String($(this).data("post-type")) === String(postType);
            }).css("opacity", checked ? "1" : "0.45");
        });
    }

    $(document).on("change", 'input[name="enabled_post_types[]"]', function() {
        updatePostTypeDim();
    });

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

    // ---- 固定ページの一括選択 ----

    $("#check-all-pages").click(function(e) {
        e.preventDefault();
        $('input[name="enabled_pages[]"]').prop("checked", true).trigger("change");
    });

    $("#uncheck-all-pages").click(function(e) {
        e.preventDefault();
        $('input[name="enabled_pages[]"]').prop("checked", false).trigger("change");
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

    // 順序リストの行をPHP出力と同じ構造で生成する
    function createPageItem(pageId, title) {
        var $item = $("<div>")
            .addClass("page-item")
            .attr("data-page-id", pageId)
            .css({
                display: "flex",
                "align-items": "center",
                padding: "10px",
                margin: "8px 0",
                background: "#fff",
                border: "1px solid #ccc",
                "border-radius": "3px"
            });

        $("<span>")
            .css({ flex: "1", "font-weight": "500" })
            .text(title + " (ID: " + pageId + ")")
            .appendTo($item);

        var $buttons = $("<div>").css("margin-left", "10px").appendTo($item);

        $("<button>", { type: "button", html: "&uarr;" })
            .addClass("page-move-up button button-small")
            .attr("data-page-id", pageId)
            .css("margin-right", "5px")
            .appendTo($buttons);

        $("<button>", { type: "button", html: "&darr;" })
            .addClass("page-move-down button button-small")
            .attr("data-page-id", pageId)
            .appendTo($buttons);

        return $item;
    }

    // 固定ページのチェックと順序リストをリアルタイム連動させる
    $(document).on("change", 'input[name="enabled_pages[]"]', function() {
        var $checkbox = $(this);

        // 順序リストに表示するのは親ページのみ
        if (String($checkbox.data("parent")) !== "0") {
            return;
        }

        var pageId = $checkbox.val();
        var $existing = $("#page-order-list .page-item").filter(function() {
            return String($(this).data("page-id")) === String(pageId);
        });

        if ($checkbox.prop("checked")) {
            if ($existing.length === 0) {
                $("#page-order-list").append(createPageItem(pageId, String($checkbox.data("title"))));
            }
        } else {
            $existing.remove();
        }

        updatePageOrder();
        updatePageButtons();
    });

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

    // ---- ドラッグ＆ドロップ並び替え（jQuery UI Sortable） ----

    if ($.fn.sortable) {
        $("#post-type-order-list").sortable({
            items: ".post-type-item",
            cursor: "move",
            update: function() {
                updateOrder();
                updateButtons();
            }
        });

        $("#page-order-list").sortable({
            items: ".page-item",
            cursor: "move",
            update: function() {
                updatePageOrder();
                updatePageButtons();
            }
        });
    }

    // ---- 初期化 ----

    updateOrder();
    updateButtons();
    updatePostTypeDim();
    updatePageOrder();
    updatePageButtons();
});
