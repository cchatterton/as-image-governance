(function ($) {
    'use strict';

    var dismissed = {};
    var activeAttachmentId = 0;

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

    function setupCollectionDropTargets() {
        var $draggables = $('.wp-list-table.media tbody tr, .attachments-browser .attachment');

        $draggables.attr('draggable', true);

        $draggables.off('dragstart.asig').on('dragstart.asig', function (event) {
            event.originalEvent.dataTransfer.setData('text/plain', getAttachmentIdFromElement(this));
        });

        $('.asig-collection-drop-target').off('.asigDrop').on('dragover.asigDrop', function (event) {
            event.preventDefault();
            $(this).addClass('asig-drop-active');
        }).on('dragleave.asigDrop drop.asigDrop', function () {
            $(this).removeClass('asig-drop-active');
        }).on('drop.asigDrop', function (event) {
            event.preventDefault();
            assignImageToCollection(
                event.originalEvent.dataTransfer.getData('text/plain'),
                $(this).data('collection-id')
            );
        });
    }

    function attachmentDetailsUrl(attachmentId) {
        return window.ASIG.attachmentUrl + '/' + attachmentId;
    }

    function maybeOpenGovernanceModal(attachmentId) {
        if (!window.ASIG || !attachmentId || dismissed[attachmentId] || activeAttachmentId === attachmentId) {
            return;
        }

        restRequest(attachmentDetailsUrl(attachmentId), 'GET').then(function (details) {
            if (!details || !details.needs_governance || dismissed[attachmentId]) {
                return;
            }

            openGovernanceModal(details);
        });
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
        if (activeAttachmentId) {
            dismissed[activeAttachmentId] = true;
        }

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

    function watchImageSelections() {
        $(document).on('click', '.attachments-browser .attachment, .media-modal .attachment, .wp-list-table.media tbody tr', function () {
            var attachmentId = getAttachmentIdFromElement(this);

            window.setTimeout(function () {
                maybeOpenGovernanceModal(attachmentId);
            }, 250);
        });

        if (window.wp && window.wp.media && window.wp.media.frame) {
            window.wp.media.frame.on('selection:toggle selection:single', function () {
                var selection = window.wp.media.frame.state().get('selection');
                var attachment = selection && selection.first ? selection.first() : null;

                if (attachment) {
                    maybeOpenGovernanceModal(attachment.get('id'));
                }
            });
        }
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
        setupCollectionDropTargets();
        bindGovernanceModal();
        watchImageSelections();

        window.setInterval(setupCollectionDropTargets, 1500);
    });
})(jQuery);
