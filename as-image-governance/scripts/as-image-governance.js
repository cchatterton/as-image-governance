(function ($) {
    'use strict';

    function assignImageToCollection(attachmentId, collectionId) {
        if (!window.ASIG || !attachmentId || !collectionId) {
            return;
        }

        window.fetch(window.ASIG.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.ASIG.nonce
            },
            body: JSON.stringify({
                attachment_id: attachmentId,
                collection_id: collectionId
            })
        });
    }

    function setupCollectionDropTargets() {
        var $rows = $('.wp-list-table.media tbody tr');
        var $collections = $('select[name="asig_collection"] option[value!=""]');

        $rows.attr('draggable', true);

        $rows.on('dragstart', function (event) {
            event.originalEvent.dataTransfer.setData('text/plain', $(this).attr('id').replace('post-', ''));
        });

        if (!$collections.length || $('.asig-collection-drop-panel').length) {
            return;
        }

        var $panel = $('<div class="asig-collection-drop-panel" aria-label="Image collection drop targets"></div>');
        $collections.each(function () {
            var $option = $(this);
            var $target = $('<button type="button" class="button asig-collection-drop-target"></button>');
            $target.text($option.text());
            $target.attr('data-collection-id', $option.val());
            $panel.append($target);
        });

        $('.tablenav.top').after($panel);

        $('.asig-collection-drop-target').on('dragover', function (event) {
            event.preventDefault();
            $(this).addClass('asig-drop-active');
        }).on('dragleave drop', function () {
            $(this).removeClass('asig-drop-active');
        }).on('drop', function (event) {
            event.preventDefault();
            assignImageToCollection(
                event.originalEvent.dataTransfer.getData('text/plain'),
                $(this).data('collection-id')
            );
        });
    }

    $(setupCollectionDropTargets);
})(jQuery);
