(function ($) {
    'use strict';

    var activeAttachmentId = 0;
    var uploadWatcherBound = false;
    var apiFetchWatcherBound = false;
    var promptedUploads = {};
    var pendingUploadPollActive = false;
    var lastExternalImageUrl = '';
    var AsigAuthorityFilter = null;
    var AsigMissingFilter = null;
    var AsigCollectionFilter = null;
    var AsigImageColorFilter = null;
    var AsigImageTagFilter = null;

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
            var $collection = $('.asig-collection-drop-target[data-collection-id="' + collectionId + '"]');
            var collectionName = $.trim($collection.text());
            $target.addClass('asig-collection-assigned');
            $collection.addClass('asig-collection-assigned-target');
            if (String(collectionId) === '0') {
                clearCollectionCell(attachmentId);
                $('.asig-assignment-status').text(window.ASIG.strings.removedFromTerms);
            } else {
                updateCollectionCell(attachmentId, collectionName);
                $('.asig-assignment-status').text(window.ASIG.strings.assignedTo.replace('%s', collectionName));
            }
            window.setTimeout(function () {
                $target.removeClass('asig-collection-assigned');
                $collection.removeClass('asig-collection-assigned-target');
            }, 1200);
        });
    }

    function updateCollectionCell(attachmentId, collectionName) {
        var $cell = $('#post-' + attachmentId + ' .column-asig_collections');
        var currentText = $.trim($cell.text());

        if (!$cell.length || !collectionName || currentText.indexOf(collectionName) !== -1) {
            return;
        }

        if (!currentText || currentText === '—') {
            $cell.text(collectionName);
            return;
        }

        $cell.text(currentText + ', ' + collectionName);
    }

    function clearCollectionCell(attachmentId) {
        $('#post-' + attachmentId + ' .column-asig_collections').text('—');
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
        if (!window.ASIG.enableCollectionUi) {
            return;
        }

        var $items = $('.wp-list-table.media tbody tr, .attachments-browser .attachment, .media-frame .attachment')
            .attr('draggable', 'true')
            .addClass('asig-draggable-image');

        if ($.fn.draggable) {
            $items.not('.asig-ui-draggable').addClass('asig-ui-draggable').draggable({
                appendTo: 'body',
                helper: 'clone',
                opacity: 0.8,
                revert: 'invalid',
                zIndex: 100000,
                start: function () {
                    $(this).addClass('asig-dragging');
                },
                stop: function () {
                    $(this).removeClass('asig-dragging');
                }
            });
        }

        if ($.fn.droppable) {
            $('.asig-collection-drop-target').not('.asig-ui-droppable').addClass('asig-ui-droppable').droppable({
                accept: '.asig-draggable-image',
                hoverClass: 'asig-drop-active',
                tolerance: 'pointer',
                drop: function (event, ui) {
                    $(this).data('asig-just-dropped', true);
                    window.setTimeout(function (target) {
                        $(target).removeData('asig-just-dropped');
                    }, 500, this);
                    assignImageToCollection(
                        getAttachmentIdFromElement(ui.draggable),
                        $(this).data('collection-id')
                    );
                }
            });
        }
    }

    function bindCollectionAssignment() {
        if (!window.ASIG.enableCollectionUi) {
            return;
        }

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
            $(this).data('asig-just-dropped', true);
            window.setTimeout(function (target) {
                $(target).removeData('asig-just-dropped');
            }, 500, this);
            assignImageToCollection(
                event.originalEvent.dataTransfer.getData('text/plain'),
                $(this).data('collection-id')
            );
        });

        $(document).on('click', '.asig-collection-drop-target', function () {
            if ($(this).data('asig-just-dropped')) {
                return;
            }

            var filterUrl = $(this).data('filter-url');

            if (filterUrl) {
                window.location.href = filterUrl;
            }
        });

        $(document).on('click', '.asig-recount-link', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.location.href = $(this).data('recount-url');
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

        restRequest(attachmentDetailsUrl(attachmentId), 'GET').then(function (details) {
            if (!details) {
                return;
            }

            promptedUploads[attachmentId] = true;

            if (!details.needs_governance) {
                return;
            }

            openGovernanceModal(details);
        });
    }

    function pollPendingUploads() {
        if (pendingUploadPollActive || activeAttachmentId || !window.ASIG || !window.ASIG.pendingUploadsUrl) {
            return;
        }

        pendingUploadPollActive = true;

        restRequest(window.ASIG.pendingUploadsUrl, 'GET').then(function (response) {
            pendingUploadPollActive = false;

            if (response && response.attachment_id) {
                maybeOpenGovernanceModalForUpload(parseInt(response.attachment_id, 10));
            }
        }).catch(function () {
            pendingUploadPollActive = false;
        });
    }

    function watchNewUploads() {
        if (uploadWatcherBound || !window.wp || !window.wp.Uploader || !window.wp.Uploader.queue) {
            return;
        }

        uploadWatcherBound = true;

        window.wp.Uploader.queue.on('add', function (attachment) {
            waitForUploadedAttachmentId(attachment);
            if (attachment.on) {
                attachment.on('change:id change:uploading change:type change:mime', function () {
                    waitForUploadedAttachmentId(attachment);
                });
            }
        });
    }

    function watchRestMediaUploads() {
        if (apiFetchWatcherBound || !window.wp || !window.wp.apiFetch || !window.wp.apiFetch.use) {
            return;
        }

        apiFetchWatcherBound = true;

        window.wp.apiFetch.use(function (options, next) {
            var requestOptions = options || {};
            var path = requestOptions.path || '';
            var url = requestOptions.url || '';
            var method = String(requestOptions.method || 'GET').toUpperCase();
            var normalizedPath = String(path).replace(/^\/+/, '');
            var isMediaCreate = 'POST' === method && (normalizedPath.indexOf('wp/v2/media') === 0 || String(url).indexOf('/wp/v2/media') !== -1);

            return next(options).then(function (response) {
                if (isMediaCreate && response && response.id && (!response.mime_type || String(response.mime_type).indexOf('image/') === 0)) {
                    maybeOpenGovernanceModalForUpload(parseInt(response.id, 10));
                }

                return response;
            });
        });
    }

    function waitForUploadedAttachmentId(attachment) {
        var attempts = 0;
        var timer = window.setInterval(function () {
            var attachmentId = attachment.get ? parseInt(attachment.get('id'), 10) : 0;
            var type = attachment.get ? attachment.get('type') : '';
            var mime = attachment.get ? attachment.get('mime') : '';
            var uploading = attachment.get ? attachment.get('uploading') : false;

            attempts++;

            if (attachmentId && ('image' === type || String(mime).indexOf('image/') === 0) && !uploading) {
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

    function buildTagValue(tags) {
        return (tags || []).join(', ');
    }

    function fieldHelp(key) {
        var tooltips = window.ASIG.strings.tooltips || {};
        var text = tooltips[key] || '';

        if (!text) {
            return '';
        }

        return '<span class="asig-field-help" tabindex="0" aria-label="' + escapeHtml(text) + '"><span class="dashicons dashicons-editor-help"></span><span class="asig-field-help__tip">' + escapeHtml(text) + '</span></span>';
    }

    function fieldLabel(label, key) {
        return '<span class="asig-field-label">' + escapeHtml(label) + fieldHelp(key) + '</span>';
    }

    function openGovernanceModal(details) {
        activeAttachmentId = details.attachment_id;
        $('.asig-governance-modal').remove();

        if ((!details.source || !String(details.source).trim()) && lastExternalImageUrl) {
            details.source = lastExternalImageUrl;
        }

        var modal = [
            '<div class="asig-governance-modal" role="dialog" aria-modal="true">',
                '<div class="asig-governance-modal__panel">',
                    '<h2>' + escapeHtml(window.ASIG.strings.modalTitle) + '</h2>',
                    '<p>' + escapeHtml(window.ASIG.strings.modalIntro) + '</p>',
                    '<form>',
                        '<label>' + fieldLabel(window.ASIG.strings.source, 'source') + '<input type="text" name="source" value="' + escapeHtml(details.source || '') + '"></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.authorityLevel, 'authorityLevel') + '<select name="authority_level">' + buildAuthorityOptions(details.authority_level) + '</select></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.expiry, 'expiry') + '<input type="date" name="expiry_date" value="' + escapeHtml(details.expiry_date || '') + '"></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.authorityNotes, 'authorityNotes') + '<input type="text" name="authority_notes" value="' + escapeHtml(details.authority_notes || '') + '"></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.attribution, 'attribution') + '<input type="text" name="attribution" value="' + escapeHtml(details.attribution || '') + '"></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.imageColors, 'imageColors') + '<input type="text" name="image_colors" value="' + escapeHtml(buildTagValue(details.image_colors)) + '"></label>',
                        '<label>' + fieldLabel(window.ASIG.strings.imageTags, 'imageTags') + '<input type="text" name="image_tags" value="' + escapeHtml(buildTagValue(details.image_tags)) + '"></label>',
                        '<fieldset><legend>' + fieldLabel(window.ASIG.strings.collections, 'collections') + '</legend>' + buildCollectionOptions(details.collections) + '</fieldset>',
                        '<div class="asig-governance-modal__actions">',
                            '<button type="submit" class="button button-primary">' + escapeHtml(window.ASIG.strings.save) + '</button>',
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
                expiry_date: $form.find('[name="expiry_date"]').val(),
                authority_notes: $form.find('[name="authority_notes"]').val(),
                attribution: $form.find('[name="attribution"]').val(),
                image_colors: $form.find('[name="image_colors"]').val(),
                image_tags: $form.find('[name="image_tags"]').val(),
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

    function bindExternalUrlCapture() {
        $(document).on('input change', 'input[type="url"], input[placeholder*="URL"], input[aria-label*="URL"]', function () {
            var value = $.trim($(this).val());

            if (/^https?:\/\/.+\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i.test(value)) {
                lastExternalImageUrl = value;
            }
        });
    }

    function setupMediaModalFilters() {
        if (!window.wp || !window.wp.media || !window.wp.media.view || !window.wp.media.view.AttachmentsBrowser || !window.wp.media.view.AttachmentFilters) {
            return;
        }

        if (window.wp.media.view.AttachmentsBrowser.prototype.asigFiltersAdded) {
            return;
        }

        var originalCreateToolbar = window.wp.media.view.AttachmentsBrowser.prototype.createToolbar;
        AsigAuthorityFilter = createAuthorityFilter();
        AsigMissingFilter = createMissingFilter();
        AsigCollectionFilter = createCollectionFilter();
        AsigImageColorFilter = createTermFilter('asig-image-color-filter', window.ASIG.strings.allImageColors, 'asig_image_color', window.ASIG.imageColors || []);
        AsigImageTagFilter = createTermFilter('asig-image-tag-filter', window.ASIG.strings.allImageTags, 'asig_image_tag', window.ASIG.imageTags || []);

        window.wp.media.view.AttachmentsBrowser.prototype.asigFiltersAdded = true;

        window.wp.media.view.AttachmentsBrowser.prototype.createToolbar = function () {
            originalCreateToolbar.apply(this, arguments);

            if (!this.toolbar || !this.collection || !this.collection.props) {
                return;
            }

            this.toolbar.set(
                'asigAuthorityFilter',
                new AsigAuthorityFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -75
                }).render()
            );

            this.toolbar.set(
                'asigMissingFilter',
                new AsigMissingFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -74
                }).render()
            );

            this.toolbar.set(
                'asigCollectionFilter',
                new AsigCollectionFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -73
                }).render()
            );

            this.toolbar.set(
                'asigImageColorFilter',
                new AsigImageColorFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -72
                }).render()
            );

            this.toolbar.set(
                'asigImageTagFilter',
                new AsigImageTagFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -71
                }).render()
            );
        };
    }

    function createAuthorityFilter() {
        return window.wp.media.view.AttachmentFilters.extend({
            id: 'asig-authority-filter',
            createFilters: function () {
                var filters = {
                    all: {
                        text: window.ASIG.strings.allAuthority,
                        props: {
                            asig_authority_level: ''
                        },
                        priority: 10
                    }
                };

                $.each(window.ASIG.authorityLevels || {}, function (value, label) {
                    filters['asig_authority_' + value] = {
                        text: label,
                        props: {
                            asig_authority_level: value
                        },
                        priority: 20 + parseInt(value, 10)
                    };
                });

                this.filters = filters;
            }
        });
    }

    function createMissingFilter() {
        return window.wp.media.view.AttachmentFilters.extend({
            id: 'asig-missing-filter',
            createFilters: function () {
                this.filters = {
                    all: {
                        text: window.ASIG.strings.allGovernance,
                        props: {
                            asig_missing: ''
                        },
                        priority: 10
                    },
                    source: {
                        text: window.ASIG.strings.missingSource,
                        props: {
                            asig_missing: 'source'
                        },
                        priority: 20
                    },
                    attribution: {
                        text: window.ASIG.strings.missingAttribution,
                        props: {
                            asig_missing: 'attribution'
                        },
                        priority: 30
                    }
                };
            }
        });
    }

    function createCollectionFilter() {
        return createTermFilter('asig-collection-filter', window.ASIG.strings.allCollections, 'asig_collection', window.ASIG.collections || []);
    }

    function createTermFilter(id, allLabel, propName, terms) {
        return window.wp.media.view.AttachmentFilters.extend({
            id: id,
            createFilters: function () {
                var allProps = {};
                var filters = {
                    all: {
                        text: allLabel,
                        props: allProps,
                        priority: 10
                    }
                };

                allProps[propName] = '';

                $.each(terms || [], function (index, term) {
                    var props = {};
                    props[propName] = term.id;

                    filters[propName + '_' + term.id] = {
                        text: term.name,
                        props: props,
                        priority: 20 + index
                    };
                });

                this.filters = filters;
            }
        });
    }

    $(function () {
        setupMediaModalFilters();
        watchRestMediaUploads();
        setupCollectionDraggables();
        bindCollectionAssignment();
        bindGovernanceModal();
        bindExternalUrlCapture();
        watchNewUploads();
        pollPendingUploads();

        window.setInterval(function () {
            setupCollectionDraggables();
            watchRestMediaUploads();
            watchNewUploads();
            pollPendingUploads();
        }, 1500);
    });
})(jQuery);
