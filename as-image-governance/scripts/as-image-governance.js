(function ($) {
    'use strict';

    var activeAttachmentId = 0;
    var uploadWatcherBound = false;
    var promptedUploads = {};

    function restRequest(url, method, data) {
        return window.fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.ASIG.nonce
            },
            body: data ? JSON.stringify(data) : undefined
        }).then(function (response) {
            return response.json();
        });
    }

    function assignImageToCollection(attachmentId, collectionId) {
        if (!window.ASIG || !attachmentId || !collectionId) {
            return;
        }

        restRequest(window.ASIG.assignCollectionUrl, 'POST', {
            attachment_id: attachmentId,
            collection_id: collectionId
        }).then(function () {
            var $target = $('[data-id="' + attachmentId + '"], #post-' + attachmentId);
            $target.addClass('asig-collection-assigned');
            window.setTimeout(function () {
                $target.removeClass('asig-collection-assigned');
            }, 1200);
        });
    }

    function getAttachmentIdFromElement(element) {
        var $element = $(element);
        var dataId = $element.attr('data-id');

        if (dataId) {
            return parseInt(dataId, 10);
        }

        var rowId = $element.attr('id');

        if (rowId && rowId.indexOf('post-') === 0) {
            return parseInt(rowId.replace('post-', ''), 10);
        }

        return 0;
    }

    function setupCollectionDraggables() {
        $('.wp-list-table.media tbody tr, .attachments-browser .attachment, .media-frame .attachment')
            .attr('draggable', 'true')
            .addClass('asig-draggable-image');
    }

    function bindCollectionAssignment() {
        $(document).on('dragstart.asig', '.wp-list-table.media tbody tr, .attachments-browser .attachment, .media-frame .attachment', function (event) {
            var attachmentId = getAttachmentIdFromElement(this);

            if (!attachmentId) {
                return;
            }

            event.originalEvent.dataTransfer.effectAllowed = 'copy';
            event.originalEvent.dataTransfer.setData('text/plain', attachmentId);
        });

        $(document).on('dragover.asigDrop', '.asig-collection-drop-target', function (event) {
            event.preventDefault();
            $(this).addClass('asig-drop-active');
        });

        $(document).on('dragleave.asigDrop drop.asigDrop', '.asig-collection-drop-target', function () {
            $(this).removeClass('asig-drop-active');
        });

        $(document).on('drop.asigDrop', '.asig-collection-drop-target', function (event) {
            event.preventDefault();
            assignImageToCollection(
                event.originalEvent.dataTransfer.getData('text/plain'),
                $(this).data('collection-id')
            );
        });

        $(document).on('click', '.asig-assign-selected', function () {
            var collectionId = $('#asig-selected-collection').val();

            getSelectedAttachmentIds().forEach(function (attachmentId) {
                assignImageToCollection(attachmentId, collectionId);
            });
        });
    }

    function getSelectedAttachmentIds() {
        var ids = [];

        $('.wp-list-table.media tbody input[name="media[]"]:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });

        $('.attachments-browser .attachment.selected, .media-frame .attachment.selected').each(function () {
            var attachmentId = getAttachmentIdFromElement(this);

            if (attachmentId) {
                ids.push(attachmentId);
            }
        });

        return ids.filter(function (value, index, list) {
            return value && list.indexOf(value) === index;
        });
    }

    function attachmentDetailsUrl(attachmentId) {
        return window.ASIG.attachmentUrl + '/' + attachmentId;
    }

    function maybeOpenGovernanceModalForUpload(attachmentId) {
        if (!window.ASIG || !attachmentId || promptedUploads[attachmentId] || activeAttachmentId === attachmentId) {
            return;
        }

        promptedUploads[attachmentId] = true;

        restRequest(attachmentDetailsUrl(attachmentId), 'GET').then(function (details) {
            if (!details || !details.needs_governance) {
                return;
            }

            openGovernanceModal(details);
        });
    }

    function watchNewUploads() {
        if (uploadWatcherBound || !window.wp || !window.wp.Uploader || !window.wp.Uploader.queue) {
            return;
        }

        uploadWatcherBound = true;

        window.wp.Uploader.queue.on('add', function (attachment) {
            waitForUploadedAttachmentId(attachment);
        });
    }

    function waitForUploadedAttachmentId(attachment) {
        var attempts = 0;
        var timer = window.setInterval(function () {
            var attachmentId = attachment.get ? parseInt(attachment.get('id'), 10) : 0;
            var type = attachment.get ? attachment.get('type') : '';
            var uploading = attachment.get ? attachment.get('uploading') : false;

            attempts++;

            if (attachmentId && 'image' === type && !uploading) {
                window.clearInterval(timer);
                maybeOpenGovernanceModalForUpload(attachmentId);
            }

            if (attempts > 80) {
                window.clearInterval(timer);
            }
        }, 250);
    }

    function buildAuthorityOptions(selected) {
        var html = '';

        $.each(window.ASIG.authorityLevels || {}, function (value, label) {
            html += '<option value="' + escapeHtml(value) + '"' + (String(selected) === String(value) ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        });

        return html;
    }

    function buildCollectionOptions(selected) {
        var selectedIds = selected || [];
        var collections = window.ASIG.collections || [];

        if (!collections.length) {
            return '<p class="asig-modal-note">' + escapeHtml(window.ASIG.strings.createCollection) + '</p>';
        }

        return collections.map(function (collection) {
            var checked = selectedIds.indexOf(collection.id) !== -1 ? ' checked' : '';
            return '<label><input type="checkbox" name="collections" value="' + collection.id + '"' + checked + '> ' + escapeHtml(collection.name) + '</label>';
        }).join('');
    }

    function openGovernanceModal(details) {
        activeAttachmentId = details.attachment_id;
        $('.asig-governance-modal').remove();

        var modal = [
            '<div class="asig-governance-modal" role="dialog" aria-modal="true">',
                '<div class="asig-governance-modal__panel">',
                    '<button type="button" class="asig-governance-modal__close" aria-label="' + escapeHtml(window.ASIG.strings.dismiss) + '">&times;</button>',
                    '<h2>' + escapeHtml(window.ASIG.strings.modalTitle) + '</h2>',
                    '<p>' + escapeHtml(window.ASIG.strings.modalIntro) + '</p>',
                    '<form>',
                        '<label>' + escapeHtml(window.ASIG.strings.source) + '<input type="text" name="source" value="' + escapeHtml(details.source || '') + '"></label>',
                        '<label>' + escapeHtml(window.ASIG.strings.authorityLevel) + '<select name="authority_level">' + buildAuthorityOptions(details.authority_level) + '</select></label>',
                        '<label>' + escapeHtml(window.ASIG.strings.authorityNotes) + '<textarea name="authority_notes" rows="4">' + escapeHtml(details.authority_notes || '') + '</textarea></label>',
                        '<label>' + escapeHtml(window.ASIG.strings.attribution) + '<textarea name="attribution" rows="4">' + escapeHtml(details.attribution || '') + '</textarea></label>',
                        '<fieldset><legend>' + escapeHtml(window.ASIG.strings.collections) + '</legend>' + buildCollectionOptions(details.collections) + '</fieldset>',
                        '<div class="asig-governance-modal__actions">',
                            '<button type="submit" class="button button-primary">' + escapeHtml(window.ASIG.strings.save) + '</button>',
                            '<button type="button" class="button asig-governance-modal__dismiss">' + escapeHtml(window.ASIG.strings.dismiss) + '</button>',
                        '</div>',
                        '<p class="asig-governance-modal__status" aria-live="polite"></p>',
                    '</form>',
                '</div>',
            '</div>'
        ].join('');

        $('body').append(modal);
        $('.asig-governance-modal input[name="source"]').trigger('focus');
    }

    function closeGovernanceModal() {
        activeAttachmentId = 0;
        $('.asig-governance-modal').remove();
    }

    function bindGovernanceModal() {
        $(document).on('click', '.asig-governance-modal__close, .asig-governance-modal__dismiss', closeGovernanceModal);

        $(document).on('submit', '.asig-governance-modal form', function (event) {
            event.preventDefault();

            var $form = $(this);
            var collections = [];

            $form.find('input[name="collections"]:checked').each(function () {
                collections.push(parseInt($(this).val(), 10));
            });

            restRequest(attachmentDetailsUrl(activeAttachmentId), 'PUT', {
                source: $form.find('[name="source"]').val(),
                authority_level: $form.find('[name="authority_level"]').val(),
                authority_notes: $form.find('[name="authority_notes"]').val(),
                attribution: $form.find('[name="attribution"]').val(),
                collections: collections
            }).then(function () {
                $form.find('.asig-governance-modal__status').text(window.ASIG.strings.saved);
                window.setTimeout(closeGovernanceModal, 700);
            });
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    $(function () {
        setupCollectionDraggables();
        bindCollectionAssignment();
        bindGovernanceModal();
        watchNewUploads();

        window.setInterval(function () {
            setupCollectionDraggables();
            watchNewUploads();
        }, 1500);
    });
})(jQuery);
