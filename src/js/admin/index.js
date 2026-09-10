import 'select2/dist/js/select2.full.min.js';
import 'select2/dist/css/select2.min.css';
import { collapseItem, fetchRequest, searchUser, searchProduct, searchCategory, searchBagistoCustomer } from '../actions';

document.addEventListener('DOMContentLoaded', function () {
    const i18n = wkwctobagAdminObj.i18n || {};
    const activeSyncs = {};
    let statusPollingInterval = null;
    let hadActiveSyncs = false;
    const syncMessageKeys = {
        user: 'syncing_users',
        customer: 'syncing_customers',
        product: 'syncing_products',
        category: 'syncing_categories',
        order: 'syncing_orders',
        tax: 'syncing_taxes',
        attribute: 'syncing_attributes'
    };

    // Log Retention Purge Toggle Logic
    const logRetentionSelect = document.getElementById('wkwctobag_log_retention');
    const purgeContainer = document.getElementById('wkwctobag-purge-logs-container');
    if (logRetentionSelect && purgeContainer) {
        logRetentionSelect.addEventListener('change', function () {
            if (logRetentionSelect.value === 'never') {
                purgeContainer.style.display = 'none';
            } else {
                purgeContainer.style.display = 'block';
            }
        });
    }

    // Test Connection Button Logic
    const testConnectionBtns = document.querySelectorAll('.wkwctobag-test-connection-btn');
    if (testConnectionBtns.length > 0) {
        testConnectionBtns.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                const siteUrl = document.getElementById('site_url')?.value || '';
                const adminToken = document.getElementById('admin_token')?.value || '';

                if (!siteUrl) {
                    showToast('Store URL is required.', 'warning');
                    return;
                }

                const originalText = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 2s linear infinite;"></span> Testing...';
                this.disabled = true;

                const bodyData = {
                    action: 'wkwctobag_test_connection',
                    nonce: wkwctobagAdminObj.nonce,
                    site_url: siteUrl,
                    admin_token: adminToken
                };

                fetchRequest(wkwctobagAdminObj.ajaxurl, 'POST', bodyData, {
                    'Content-Type': 'application/x-www-form-urlencoded'
                })
                    .then(response => {
                        this.innerHTML = originalText;
                        this.disabled = false;

                        if (response.success) {
                            showToast(response.data.message || 'Connection Successful!', 'success');
                        } else {
                            showToast(response.data.message || 'Connection Failed.', 'warning');
                        }
                    })
                    .catch(error => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        showToast('An unexpected error occurred while testing the connection.', 'warning');
                        console.error(error);
                    });
            });
        });
    }


    // Auto-check connection status on page load (connection page only).
    const liveStatusBadge = document.getElementById('wkwctobag-live-status');
    if (liveStatusBadge) {
        const setLiveStatus = (ok, message) => {
            liveStatusBadge.classList.remove('disconnected', 'wkwctobag-status-checking');
            if (ok) {
                liveStatusBadge.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Connected';
            } else {
                liveStatusBadge.classList.add('disconnected');
                liveStatusBadge.innerHTML = `<span class="dashicons dashicons-dismiss"></span> ${message || 'Disconnected'}`;
            }
        };

        fetchRequest(wkwctobagAdminObj.ajaxurl, 'POST', {
            action: 'wkwctobag_test_connection',
            nonce: wkwctobagAdminObj.nonce

        }, {
            'Content-Type': 'application/x-www-form-urlencoded'
        })
            .then(response => {
                if (response.success) {
                    setLiveStatus(true);
                } else {
                    const msg = (response.data && response.data.message) ? response.data.message : 'Disconnected';
                    setLiveStatus(false, msg);
                }
            })
            .catch(() => {
                setLiveStatus(false, 'Could not reach API');
            });
    }

    const togglePasswordBtn = document.getElementById('wkwctobag-toggle-password-btn');
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const passwordInput = document.getElementById('admin_token');
            const icon = this.querySelector('.dashicons');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            }
        });
    }

    const updateModal = document.getElementById('wkwctobag-update-connection-modal');
    if (updateModal) {
        document.getElementById('wkwctobag-update-connection-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            updateModal.style.display = 'flex';
            const passwordInput = document.getElementById('update_admin_token');
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.focus();
            }
        });

        const closeUpdateModal = () => { updateModal.style.display = 'none'; };
        document.getElementById('wkwctobag-close-update-modal')?.addEventListener('click', closeUpdateModal);
        document.getElementById('wkwctobag-cancel-update')?.addEventListener('click', closeUpdateModal);

        document.getElementById('wkwctobag-update-toggle-password-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            const passwordInput = document.getElementById('update_admin_token');
            const icon = this.querySelector('.dashicons');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            }
        });
    }

    const pluginNotices = document.querySelectorAll('.notice-success, .notice-error');
    if (pluginNotices.length > 0) {
        pluginNotices.forEach(notice => {
            setTimeout(() => {
                notice.style.transition = 'opacity 0.4s ease-out';
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 400);
            }, 4000);
        });
    }

    if (window.location.search.includes('page=wkwctobag-logs')) {
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('delete-log')) {
                url.searchParams.delete('delete-log');
                url.searchParams.delete('_wpnonce');
                window.history.replaceState({ path: url.href }, '', url.href);
            }
        }

        setTimeout(() => {
            const notice = document.getElementById('wkwctobag-log-delete-success');
            if (notice) {
                notice.style.transition = 'opacity 0.5s ease';
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 500);
            }
        }, 4000);
    }

    const injectAttributePageCheckboxes = () => {
        if (!window.location.search.includes('page=product_attributes')) {
            return;
        }

        const table = document.querySelector('table.wp-list-table') || document.querySelector('table.widefat');
        if (!table) return;

        if (table.querySelector('.wkwctobag-cb-column')) return;

        const toolbar = document.createElement('div');
        toolbar.className = 'tablenav top wkwctobag-attribute-bulk-nav';
        toolbar.style.margin = '10px 0';
        toolbar.style.display = 'flex';
        toolbar.style.alignItems = 'center';
        toolbar.style.gap = '8px';
        toolbar.innerHTML = `
            <div class="alignleft actions bulkactions">
                <select name="action" id="wkwctobag-attribute-bulk-action" class="wkwctobag-ui-select" style="height: 32px; font-size: 13px; min-width: 150px;">
                    <option value="-1">Bulk actions</option>
                    <option value="wkwctobag_bulk_sync_attributes">Sync to Bagisto</option>
                </select>
                <input type="button" id="wkwctobag-attribute-bulk-apply" class="button action" value="Apply">
            </div>
        `;

        table.parentNode.insertBefore(toolbar, table);

        const getColumnIndices = () => {
            const headers = Array.from(table.querySelectorAll('thead th'));
            const slugIdx = headers.findIndex(th => th.textContent.toLowerCase().includes('slug') || th.id === 'slug');
            const termsIdx = headers.findIndex(th => th.textContent.toLowerCase().includes('terms') || th.id === 'terms');
            return { slugIdx, termsIdx };
        };

        let { slugIdx, termsIdx } = getColumnIndices();

        const theadTr = table.querySelector('thead tr');
        if (theadTr) {
            const th = document.createElement('th');
            th.className = 'manage-column column-cb check-column wkwctobag-cb-column';
            th.innerHTML = '<input type="checkbox" id="wkwctobag-cb-select-all">';
            theadTr.insertBefore(th, theadTr.firstChild);
        }

        const tfootTr = table.querySelector('tfoot tr');
        if (tfootTr) {
            const th = document.createElement('th');
            th.className = 'manage-column column-cb check-column wkwctobag-cb-column';
            th.innerHTML = '<input type="checkbox" id="wkwctobag-cb-select-all-2">';
            tfootTr.insertBefore(th, tfootTr.firstChild);
        }

        const updatedIndices = getColumnIndices();
        slugIdx = updatedIndices.slugIdx;
        termsIdx = updatedIndices.termsIdx;

        if (theadTr && termsIdx !== -1) {
            const termsHeader = theadTr.cells[termsIdx];
            const mappingHeader = document.createElement('th');
            mappingHeader.id = 'wkwctobag-bagisto-mapping';
            mappingHeader.className = 'manage-column column-bagisto-mapping';
            mappingHeader.textContent = 'Bagisto ID';
            theadTr.insertBefore(mappingHeader, termsHeader);
        }

        if (tfootTr && termsIdx !== -1) {
            const termsFootHeader = tfootTr.cells[termsIdx];
            const mappingFootHeader = document.createElement('th');
            mappingFootHeader.className = 'manage-column column-bagisto-mapping';
            mappingFootHeader.textContent = 'Bagisto Mapping';
            tfootTr.insertBefore(mappingFootHeader, termsFootHeader);
        }

        const tbodyRows = table.querySelectorAll('tbody tr');
        tbodyRows.forEach(tr => {
            const editLink = tr.querySelector('.row-actions .edit a') || tr.querySelector('a[href*="edit="]');
            if (!editLink) {
                const td = document.createElement('th');
                td.className = 'wkwctobag-cb-column';
                tr.insertBefore(td, tr.firstChild);
                return;
            }

            const urlParams = new URLSearchParams(editLink.href.split('?')[1]);
            const attributeId = urlParams.get('edit');

            if (attributeId) {
                const td = document.createElement('th');
                td.className = 'check-column wkwctobag-cb-column';
                td.innerHTML = `<input type="checkbox" name="wkwctobag_attribute_ids[]" value="${attributeId}">`;
                tr.insertBefore(td, tr.firstChild);
                tr.dataset.attributeId = attributeId;
            } else {
                const td = document.createElement('th');
                td.className = 'wkwctobag-cb-column';
                tr.insertBefore(td, tr.firstChild);
            }

            if (termsIdx !== -1) {
                const slugCell = tr.cells[slugIdx];
                const slug = slugCell ? slugCell.textContent.trim() : '';

                const termsCell = tr.cells[termsIdx];
                const mappingCell = document.createElement('td');
                mappingCell.className = 'column-bagisto-mapping';

                const taxonomy = 'pa_' + slug;
                const mappingId = wkwctobagAdminObj.attribute_mappings && wkwctobagAdminObj.attribute_mappings[taxonomy];

                if (mappingId) {
                    mappingCell.innerHTML = `
                        <span class="wkwctobag-badge wkwctobag-badge-success">
                            #${mappingId}
                        </span>
                    `;
                } else {
                    mappingCell.innerHTML = `
                        <span class="wkwctobag-badge wkwctobag-badge-secondary">
                            Not Synced
                        </span>
                    `;
                }

                tr.insertBefore(mappingCell, termsCell);
            }
        });

        const selectAll1 = document.getElementById('wkwctobag-cb-select-all');
        const selectAll2 = document.getElementById('wkwctobag-cb-select-all-2');
        const checkboxes = table.querySelectorAll('tbody input[name="wkwctobag_attribute_ids[]"]');

        const handleSelectAll = (checked) => {
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
            if (selectAll1) selectAll1.checked = checked;
            if (selectAll2) selectAll2.checked = checked;
        };

        if (selectAll1) {
            selectAll1.addEventListener('change', function () {
                handleSelectAll(this.checked);
            });
        }
        if (selectAll2) {
            selectAll2.addEventListener('change', function () {
                handleSelectAll(this.checked);
            });
        }
    };

    injectAttributePageCheckboxes();

    const toastContainer = document.createElement('div');
    toastContainer.className = 'wkwctobag-toast-container';
    document.body.appendChild(toastContainer);

    const showToast = (message, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = `wkwctobag-toast wkwctobag-toast-${type}`;
        toast.innerHTML = `<div class="wkwctobag-toast-message">${message}</div>`;
        toastContainer.appendChild(toast);
        adjustToastPosition();
        setTimeout(() => toast.classList.add('wkwctobag-toast-show'), 100);
        setTimeout(() => {
            toast.classList.remove('wkwctobag-toast-show');
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    };

    let globalWidget = null;
    let globalWidgetMinimized = false;
    let dismissTimeout = null;
    const globalSyncState = {};

    const forceDismissWidget = () => {
        if (globalWidget) {
            globalWidget.classList.remove('wkwctobag-card-active');
            setTimeout(() => {
                globalWidget.classList.remove('wkwctobag-card-completed');
                toggleMinimize(false);
                adjustToastPosition();
            }, 400);
        }
    };

    const adjustToastPosition = () => {
        if (!toastContainer) return;
        if (!globalWidget || !globalWidget.classList.contains('wkwctobag-card-active')) {
            toastContainer.style.bottom = '20px';
            return;
        }

        if (globalWidgetMinimized) {
            toastContainer.style.bottom = '96px';
        } else {
            const cardHeight = globalWidget.offsetHeight || 200;
            toastContainer.style.bottom = `${cardHeight + 40}px`;
        }
    };

    const initGlobalProgressWidget = () => {
        if (globalWidget) return;

        globalWidget = document.createElement('div');
        globalWidget.className = 'wkwctobag-global-progress-card';
        globalWidget.innerHTML = `
            <div class="wkwctobag-card-header">
                <div class="wkwctobag-card-title-group">
                    <div class="wkwctobag-card-pulse-dot"></div>
                    <h4 class="wkwctobag-card-title">Bagisto Sync for WooCommerce</h4>
                </div>
                <div class="wkwctobag-card-controls">
                    <button type="button" class="wkwctobag-card-minimize" title="Minimize">&minus;</button>
                    <button type="button" class="wkwctobag-card-close" title="Close" style="display: none;">&times;</button>
                </div>
            </div>
            <div class="wkwctobag-card-body"></div>
            <div class="wkwctobag-badge-content">
                <svg class="wkwctobag-badge-spinner" width="38" height="38" viewBox="0 0 38 38">
                    <circle cx="19" cy="19" r="16" fill="none" stroke="rgba(255, 255, 255, 0.1)" stroke-width="3"></circle>
                    <circle class="wkwctobag-badge-ring" cx="19" cy="19" r="16" fill="none" stroke="#3b82f6" stroke-width="3" stroke-dasharray="100.53" stroke-dashoffset="100.53" stroke-linecap="round"></circle>
                </svg>
                <span class="wkwctobag-badge-percent">0%</span>
            </div>
        `;

        document.body.appendChild(globalWidget);

        const minimizeBtn = globalWidget.querySelector('.wkwctobag-card-minimize');
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMinimize(true);
            });
        }

        const closeBtn = globalWidget.querySelector('.wkwctobag-card-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                forceDismissWidget();
            });
        }

        globalWidget.addEventListener('click', () => {
            if (globalWidgetMinimized) {
                toggleMinimize(false);
            }
        });
    };

    const toggleMinimize = (minimize) => {
        if (!globalWidget) return;
        globalWidgetMinimized = minimize;
        if (minimize) {
            globalWidget.classList.add('wkwctobag-card-minimized');
        } else {
            globalWidget.classList.remove('wkwctobag-card-minimized');
        }
        adjustToastPosition();
    };
    const updateGlobalWidget = (type, remain, total, progress = null) => {
        initGlobalProgressWidget();

        if (globalWidget && !globalWidget.classList.contains('wkwctobag-card-active')) {
            Object.keys(globalSyncState).forEach(key => {
                delete globalSyncState[key];
            });
        }

        const t = parseInt(total) || 0;
        const r = parseInt(remain) || 0;
        const synced = Math.max(0, t - r);
        let pct = t > 0 ? Math.round((synced / t) * 100) : 0;
        if (r === 0) {
            pct = 100;
        }

        const failed = progress && progress.failed ? parseInt(progress.failed) : 0;
        const done = progress && progress.done ? parseInt(progress.done) : 0;
        const stateStr = progress && progress.state ? progress.state : '';

        globalSyncState[type] = {
            remain: r,
            total: t,
            synced: synced,
            pct: pct,
            failed: failed,
            done: done,
            state: stateStr
        };

        const body = globalWidget.querySelector('.wkwctobag-card-body');
        if (!body) return;

        body.innerHTML = '';
        let totalSynced = 0;
        let totalCount = 0;
        let anyRunning = false;

        Object.keys(globalSyncState).forEach(syncType => {
            const state = globalSyncState[syncType];
            totalSynced += state.synced;
            totalCount += state.total;
            if (state.remain > 0) {
                anyRunning = true;
            }

            const itemEl = document.createElement('div');
            itemEl.className = 'wkwctobag-card-sync-item';
            const labelText = syncType === 'product' ? 'Products' : syncType === 'category' ? 'Categories' : syncType === 'tax' ? 'Taxes' : syncType === 'user' ? 'Users' : syncType === 'customer' ? 'Customers' : syncType;

            let countLabel = '';
            if (state.remain === 0) {
                if (state.failed > 0) {
                    countLabel = `${state.done} Synced, ${state.failed} Failed`;
                } else {
                    countLabel = 'Completed';
                }
            } else {
                countLabel = `${state.synced}/${state.total}`;
                if (state.failed > 0) {
                    countLabel += ` (${state.failed} Failed)`;
                }
            }

            itemEl.innerHTML = `
                <div class="wkwctobag-item-meta">
                    <span class="wkwctobag-item-label">${labelText}</span>
                    <span class="wkwctobag-item-count">${countLabel}</span>
                </div>
                <div class="wkwctobag-item-bar-group">
                    <div class="wkwctobag-item-bar-bg">
                        <div class="wkwctobag-item-bar-fill" style="width: ${state.pct}%;"></div>
                    </div>
                    <span class="wkwctobag-item-percentage">${state.pct}%</span>
                </div>
            `;
            body.appendChild(itemEl);
        });

        let overallPct = totalCount > 0 ? Math.round((totalSynced / totalCount) * 100) : 0;

        const ring = globalWidget.querySelector('.wkwctobag-badge-ring');
        const percentTxt = globalWidget.querySelector('.wkwctobag-badge-percent');
        if (ring && percentTxt) {
            const circumference = 100.53;
            const offset = circumference * (1 - overallPct / 100);
            ring.style.strokeDashoffset = offset;
            percentTxt.textContent = `${overallPct}%`;
        }

        globalWidget.classList.add('wkwctobag-card-active');

        if (!anyRunning) {
            globalWidget.classList.add('wkwctobag-card-completed');
            globalWidget.classList.remove('wkwctobag-card-failed');
            const closeBtn = globalWidget.querySelector('.wkwctobag-card-close');
            if (closeBtn) closeBtn.style.display = 'flex';

            // Clear any active dismiss timeout
            if (dismissTimeout) clearTimeout(dismissTimeout);

            // Auto dismiss after 4 seconds
            dismissTimeout = setTimeout(forceDismissWidget, 4000);
        } else {
            if (dismissTimeout) {
                clearTimeout(dismissTimeout);
                dismissTimeout = null;
            }
            globalWidget.classList.remove('wkwctobag-card-completed');
            globalWidget.classList.remove('wkwctobag-card-failed');
            const closeBtn = globalWidget.querySelector('.wkwctobag-card-close');
            if (closeBtn) closeBtn.style.display = 'none';
        }

        setTimeout(adjustToastPosition, 50);
    };

    const updateProgress = (type, remain, total, progress = null) => {
        const el = document.querySelector(`.wkwctobag-sync-status[data-sync-type="${type}"]`);
        if (el) {
            const t = parseInt(total) || 0;
            const r = parseInt(remain) || 0;
            const synced = Math.max(0, t - r);
            let pct = t > 0 ? Math.round((synced / t) * 100) : 0;
            if (r === 0) {
                pct = 100;
            }
            const messageKey = r === 0 ? `sync_completed` : (syncMessageKeys[type] || `syncing_${type}s`);
            const msg = r === 0 ? (i18n[messageKey] || i18n.sync_completed || 'Sync completed') : (i18n[messageKey] || i18n.btn_syncing || 'Syncing...');
            el.innerHTML = `<div class="wkwctobag-progress-info"><span>${msg} (${r === 0 ? t : synced}/${t})</span> <span>${pct}%</span></div>
                            <div class="wkwctobag-progress-bg"><div class="wkwctobag-progress-fill" style="width:${pct}%"></div></div>`;
            el.style.display = 'block';
        }

        // Always update the floating card
        updateGlobalWidget(type, remain, total, progress);
    };

    const disableSyncButton = (type) => {
        const btn = document.querySelector(`.wkwcbagcon-sync-${type}-btn`);
        if (btn && !btn.disabled) {
            const originalText = btn.textContent;
            activeSyncs[type] = activeSyncs[type] || {};
            activeSyncs[type].text = originalText;

            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
            btn.textContent = i18n.btn_syncing || 'Syncing...';
        }
    };

    const enableSyncButton = (type) => {
        const btn = document.querySelector(`.wkwcbagcon-sync-${type}-btn`);
        if (btn && activeSyncs[type]) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            if (activeSyncs[type].text) {
                btn.textContent = activeSyncs[type].text;
            }
        }
        const el = document.querySelector(`.wkwctobag-sync-status[data-sync-type="${type}"]`);
        if (el) el.style.display = 'none';
        delete activeSyncs[type];
    };

    const startSyncStatusPolling = () => {
        if (Object.keys(activeSyncs).length > 0) {
            hadActiveSyncs = true;
        }
        if (statusPollingInterval) return;

        statusPollingInterval = setInterval(async () => {
            if (Object.keys(activeSyncs).length > 0) {
                hadActiveSyncs = true;
            }
            try {
                const response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST', {
                    action: 'wkwctobag_check_sync_status',
                    nonce: wkwctobagAdminObj.nonce
                }, {
                    'Content-Type': 'application/x-www-form-urlencoded'
                });

                if (response.success) {
                    Object.keys(activeSyncs).forEach(type => {
                        const remaining = response.counts[type] || 0;
                        const total = response.initial_totals[type] || activeSyncs[type].total || 0;
                        const progress = response.progress && response.progress[type] ? response.progress[type] : null;

                        if (progress && progress.state === 'token_error') {
                            updateProgress(type, 0, total, progress); // stop it visually
                            showToast(progress.message || 'Token expired. Please reconnect.', 'error');
                            enableSyncButton(type);
                            delete activeSyncs[type];
                        } else if (remaining > 0) {
                            updateProgress(type, remaining, total, progress);
                        } else {
                            updateProgress(type, 0, total, progress);
                            const msg = type ? `sync_completed` : '';
                            const labelText = type === 'product' ? 'Product' : type === 'category' ? 'Category' : type === 'tax' ? 'Tax' : type === 'user' ? 'User' : type === 'customer' ? 'Customer' : type.charAt(0).toUpperCase() + type.slice(1);
                            if (progress && (progress.failed > 0 || progress.state === 'failed')) {
                                const toastType = progress.done > 0 ? 'warning' : 'error';
                                showToast(`${labelText}: ${progress.message || 'Sync failed'}`, toastType);
                            } else {
                                showToast(`${labelText}: ${i18n[msg] || i18n.sync_completed || 'Sync complete'}`, 'success');
                            }
                            setTimeout(() => {
                                enableSyncButton(type);
                            }, 1500);
                        }
                    });
                    if (!response.is_queue_running || (hadActiveSyncs && Object.keys(activeSyncs).length === 0)) {
                        clearInterval(statusPollingInterval);
                        statusPollingInterval = null;
                        setTimeout(() => {
                            forceDismissWidget();
                        }, 4000);
                        if (
                            window.location.pathname.includes('edit-tags.php') ||
                            window.location.pathname.includes('edit.php') ||
                            window.location.pathname.includes('users.php') ||
                            window.location.href.includes('page=wc-order')
                        ) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 2500);
                        }
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 5000);
    };

    if (wkwctobagAdminObj.is_queue_running) {
        fetchRequest(
            wkwctobagAdminObj.ajaxurl,
            'POST', {
            action: 'wkwctobag_check_sync_status',
            nonce: wkwctobagAdminObj.nonce
        }, {
            'Content-Type': 'application/x-www-form-urlencoded'
        }).then(res => {
            if (res.success) {
                if (Object.keys(res.counts).length > 0) {
                    hadActiveSyncs = true;
                }
                Object.keys(res.counts).forEach(type => {
                    activeSyncs[type] = { total: res.initial_totals[type] || 0 };
                    disableSyncButton(type);
                    const progress = res.progress && res.progress[type] ? res.progress[type] : null;
                    updateProgress(type, res.counts[type], res.initial_totals[type], progress);
                });
                startSyncStatusPolling();
            }
        });
    }

    const toggleLoader = (btn, show = true) => {
        const wrapper = btn.closest('.wkwctobag-bagisto-wrapper');
        const loader = wrapper ? wrapper.querySelector('.wkwctobag-loader') : null;
        if (loader) show ? loader.classList.add('wkwctobag-show') : loader.classList.remove('wkwctobag-show');
    };

    const collapsibleItems = document.querySelectorAll('.wkwctobag-collapsible-item');
    if (!!collapsibleItems) {
        collapsibleItems.forEach(item => {
            collapseItem(item);
            item.addEventListener('click', function () { collapseItem(this); });
        });
    }

    const select = document.querySelector('.wkwctobag-settings-form select[name="allowed_order_status[]"]');
    if (!!select) jQuery(select).select2({ width: '100%', dropdownAutoWidth: true });

    const excludeOrderStatus = document.querySelector('.wkwctobag-exclude-order-status');
    if (!!excludeOrderStatus) jQuery(excludeOrderStatus).select2({ width: '100%', dropdownAutoWidth: true });

    const userSelect = document.querySelector('.wkwctobag-exclude-users-select');
    if (!!userSelect) searchUser(userSelect, ['customer']);

    const customerSelect = document.querySelector('.wkwctobag-exclude-customers-select');
    if (!!customerSelect) searchUser(customerSelect, [], ['customer']);

    const productSelect = document.querySelector('.wkwctobag-exclude-products');
    if (!!productSelect) searchProduct(productSelect);

    const categorySelect = document.querySelector('.wkwctobag-exclude-categories');
    if (!!categorySelect) searchCategory(categorySelect);

    const bagistoCustomerSelect = document.querySelector('.wkwctobag-bagisto-customers-select');
    if (!!bagistoCustomerSelect) searchBagistoCustomer(bagistoCustomerSelect);

    /**
     * Bulk sync users button click handler.
     */
    const syncUserBtn = document.querySelector('.wkwcbagcon-sync-user-btn');
    if (!!syncUserBtn) {
        syncUserBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let excludeUsers = formData.getAll('wkwctobag_exclude_users[]');
                let isUpdateExistingUsers = formData.get('wkwctobag_update_existing_users');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_user_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        exclude_users: excludeUsers,
                        update_existing_users: isUpdateExistingUsers,
                        exclude_role: ['customer'],
                        type: 'user'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['user'] = { total: response.total_items };
                    disableSyncButton('user');
                    updateProgress('user', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync customers button click handler.
     */
    const syncCustomerBtn = document.querySelector('.wkwcbagcon-sync-customer-btn');
    if (!!syncCustomerBtn) {
        syncCustomerBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let excludeUsers = formData.getAll('wkwctobag_exclude_customers[]');
                let isUpdateExistingUsers = formData.get('wkwctobag_update_existing_customers');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_user_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        exclude_users: excludeUsers,
                        update_existing_users: isUpdateExistingUsers,
                        include_role: ['customer'],
                        type: 'customer'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['customer'] = { total: response.total_items };
                    disableSyncButton('customer');
                    updateProgress('customer', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync products button click handler.
     */
    const syncProductBtn = document.querySelector('.wkwcbagcon-sync-product-btn');
    if (!!syncProductBtn) {
        syncProductBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let excludeProducts = formData.getAll('wkwctobag_exclude_products[]');
                let isUpdateExistingProducts = formData.get('wkwctobag_update_existing_products');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_product_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        exclude_products: excludeProducts,
                        update_existing_products: isUpdateExistingProducts,
                        type: 'product'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['product'] = { total: response.total_items };
                    disableSyncButton('product');
                    updateProgress('product', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync attributes button click handler.
     */
    const syncAttributeBtn = document.querySelector('.wkwcbagcon-sync-attribute-btn');
    if (!!syncAttributeBtn) {
        syncAttributeBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let isUpdateExistingAttributes = formData.get('wkwctobag_update_existing_attributes');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_attribute_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        update_existing_attributes: isUpdateExistingAttributes,
                        type: 'attribute'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['attribute'] = { total: response.total_items };
                    disableSyncButton('attribute');
                    updateProgress('attribute', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync categories button click handler.
     */
    const syncCategoryBtn = document.querySelector('.wkwcbagcon-sync-category-btn');
    if (!!syncCategoryBtn) {
        syncCategoryBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let excludeCategories = formData.getAll('wkwctobag_exclude_categories[]');
                let isUpdateExistingCategories = formData.get('wkwctobag_update_existing_categories');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_category_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        exclude_categories: excludeCategories,
                        update_existing_categories: isUpdateExistingCategories,
                        type: 'category'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['category'] = { total: response.total_items };
                    disableSyncButton('category');
                    updateProgress('category', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync taxes button click handler.
     */
    const syncTaxBtn = document.querySelector('.wkwcbagcon-sync-tax-btn');
    if (!!syncTaxBtn) {
        syncTaxBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let isUpdateExistingTaxes = formData.get('wkwctobag_update_existing_taxes');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_tax_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        update_existing_taxes: isUpdateExistingTaxes,
                        type: 'tax'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['tax'] = { total: response.total_items };
                    disableSyncButton('tax');
                    updateProgress('tax', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message || 'Failed to initiate tax synchronization.', 'error');
                }
            } catch (error) {
                showToast(error.message || 'An error occurred.', 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    /**
     * Bulk sync orders button click handler.
     */
    const syncOrderBtn = document.querySelector('.wkwcbagcon-sync-order-btn');
    if (!!syncOrderBtn) {
        syncOrderBtn.addEventListener('click', async function () {
            try {
                let syncForm = document.querySelector('.wkwctobag-bulk-sync-form');
                let formData = new FormData(syncForm);
                let excludeOrderStatus = formData.getAll('wkwctobag_exclude_order_status[]');
                let isUpdateExistingOrders = formData.get('wkwctobag_update_existing_orders');

                toggleLoader(this, true);

                let response = await fetchRequest(
                    wkwctobagAdminObj.ajaxurl,
                    'POST',
                    {
                        action: 'wkwctobag_bulk_order_sync',
                        nonce: wkwctobagAdminObj.nonce,
                        exclude_order_status: excludeOrderStatus,
                        update_existing_orders: isUpdateExistingOrders,
                        type: 'order'
                    },
                    {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                );

                if (response.success) {
                    activeSyncs['order'] = { total: response.total_items };
                    disableSyncButton('order');
                    updateProgress('order', response.total_items, response.total_items);
                    showToast(response.message, 'info');
                    startSyncStatusPolling();
                } else {
                    showToast(response.message || 'Failed to initiate order synchronization.', 'error');
                }
            } catch (error) {
                showToast(error.message || 'An error occurred.', 'error');
            } finally {
                toggleLoader(this, false);
            }
        });
    }

    class WkwcBagSelectiveSyncModal {
        constructor(options) {
            this.options = {
                modalId: '',
                bulkActionValue: '',
                checkboxSelector: '',
                syncTypeKey: '',
                modalTableHeaders: [],
                rowParser: null,
                rowRenderer: null,
                onModalRendered: null,
                dataCollector: null,
                ajaxAction: '',
                ajaxDataKey: '',
                applyBtnSelector: null,
                actionSelectSelector: null,
                ...options
            };

            this.modal = document.getElementById(this.options.modalId);
            if (!this.modal) return;

            this.postsFilterForm = document.getElementById('posts-filter');
            this.doActionBtn = document.getElementById('doaction');
            this.doActionBtn2 = document.getElementById('doaction2');

            this.init();
        }

        init() {
            const self = this;

            const applyBtn = this.options.applyBtnSelector ? document.querySelector(this.options.applyBtnSelector) : null;
            if (applyBtn) {
                applyBtn.addEventListener('click', function (e) {
                    self.handleApplyClick(e, self.options.actionSelectSelector || '#wkwctobag-attribute-bulk-action');
                });
            } else {
                if (this.doActionBtn) {
                    this.doActionBtn.addEventListener('click', function (e) {
                        self.handleApplyClick(e, 'select[name="action"]');
                    });
                }

                if (this.doActionBtn2) {
                    this.doActionBtn2.addEventListener('click', function (e) {
                        self.handleApplyClick(e, 'select[name="action2"]');
                    });
                }
            }

            if (this.postsFilterForm) {
                this.postsFilterForm.addEventListener('submit', function (e) {
                    const actionSelect = document.querySelector('select[name="action"]');
                    const actionSelect2 = document.querySelector('select[name="action2"]');
                    const isTargetAction = (actionSelect && actionSelect.value === self.options.bulkActionValue) ||
                        (actionSelect2 && actionSelect2.value === self.options.bulkActionValue);
                    if (isTargetAction) {
                        e.preventDefault();
                        if (self.modal.style.display !== 'flex') {
                            if (self.doActionBtn) {
                                self.doActionBtn.click();
                            } else if (self.doActionBtn2) {
                                self.doActionBtn2.click();
                            }
                        }
                    }
                });
            }

            const closeModal = () => {
                this.modal.style.display = 'none';
                const modalBody = this.modal.querySelector('.wkwctobag-modal-body');
                if (modalBody) modalBody.innerHTML = '';
                const modalLoader = this.modal.querySelector('.wkwctobag-modal-loader');
                if (modalLoader) modalLoader.style.display = 'none';

                if (self.options.checkboxSelector) {
                    const baseSelector = self.options.checkboxSelector.replace(':checked', '');
                    const checkboxes = document.querySelectorAll(baseSelector);
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }

                const selectAllCbs = document.querySelectorAll('.manage-column input[type="checkbox"], .column-cb input[type="checkbox"]');
                selectAllCbs.forEach(cb => {
                    cb.checked = false;
                });

                const actionSelect = document.querySelector('select[name="action"]');
                if (actionSelect) actionSelect.value = '-1';
                const actionSelect2 = document.querySelector('select[name="action2"]');
                if (actionSelect2) actionSelect2.value = '-1';
            };

            const closeBtn = this.modal.querySelector('.wkwctobag-modal-close-btn');
            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            const cancelBtn = this.modal.querySelector('.wkwctobag-modal-cancel-btn');
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            const submitBtn = this.modal.querySelector('.wkwctobag-modal-submit-btn');
            if (submitBtn) {
                submitBtn.addEventListener('click', async function () {
                    const trs = self.modal.querySelectorAll('tbody tr');
                    if (trs.length === 0) {
                        showToast('No items selected.', 'error');
                        closeModal();
                        return;
                    }

                    const itemsData = [];
                    trs.forEach(tr => {
                        if (self.options.dataCollector) {
                            itemsData.push(self.options.dataCollector(tr));
                        }
                    });

                    const modalLoader = self.modal.querySelector('.wkwctobag-modal-loader');
                    if (modalLoader) modalLoader.style.display = 'flex';

                    try {
                        const payload = {
                            action: self.options.ajaxAction,
                            nonce: wkwctobagAdminObj.nonce
                        };
                        payload[self.options.ajaxDataKey] = JSON.stringify(itemsData);

                        const response = await fetchRequest(
                            wkwctobagAdminObj.ajaxurl,
                            'POST',
                            payload,
                            { 'Content-Type': 'application/x-www-form-urlencoded' }
                        );
                        if (response.success) {
                            const type = self.options.syncTypeKey;

                            // Specific User Sync count splitting logic
                            if (type === 'user') {
                                if (response.user_count > 0) {
                                    activeSyncs['user'] = { total: response.user_count };
                                    updateProgress('user', response.user_count, response.user_count);
                                }
                                if (response.customer_count > 0) {
                                    activeSyncs['customer'] = { total: response.customer_count };
                                    updateProgress('customer', response.customer_count, response.customer_count);
                                }
                            } else {
                                const totalCount = response[`${type}_count`] || response.total_items || itemsData.length;
                                activeSyncs[type] = { total: totalCount };
                                updateProgress(type, totalCount, totalCount);
                            }

                            showToast(response.message, 'info');
                            closeModal();
                            startSyncStatusPolling();
                        } else {
                            showToast(response.success === false && response.message ? response.message : 'An error occurred.', 'error');
                            closeModal();
                        }
                    } catch (error) {
                        showToast(error.message || 'An error occurred during sync submission.', 'error');
                    } finally {
                        if (modalLoader) modalLoader.style.display = 'none';
                    }
                });
            }
        }

        handleApplyClick(e, actionSelectSelector) {
            const actionSelect = document.querySelector(actionSelectSelector);
            if (actionSelect && actionSelect.value === this.options.bulkActionValue) {
                e.preventDefault();

                const checkedCheckboxes = document.querySelectorAll(this.options.checkboxSelector);
                if (checkedCheckboxes.length === 0) {
                    showToast('Please select at least one item to sync.', 'error');
                    return;
                }

                const selectedItems = Array.from(checkedCheckboxes).map(cb => {
                    const tr = cb.closest('tr');
                    const id = cb.value;
                    return this.options.rowParser(tr, id);
                });

                let rowsHtml = '';
                selectedItems.forEach(item => {
                    if (this.options.rowRenderer) {
                        rowsHtml += this.options.rowRenderer(item);
                    }
                });

                const modalBody = this.modal.querySelector('.wkwctobag-modal-body');
                if (modalBody) {
                    let ths = '';
                    this.options.modalTableHeaders.forEach(th => {
                        ths += `<th>${th}</th>`;
                    });

                    modalBody.innerHTML = `
                        <div class="wkwctobag-modal-table-container">
                            <table class="wkwctobag-modal-table">
                                <thead>
                                    <tr>
                                        ${ths}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                    `;

                    if (this.options.onModalRendered) {
                        this.options.onModalRendered(modalBody);
                    }
                }

                this.modal.style.display = 'flex';
            }
        }
    }

    // Instantiate Users Sync Modal
    new WkwcBagSelectiveSyncModal({
        modalId: 'wkwctobag-user-sync-modal',
        bulkActionValue: 'wkwctobag_bulk_sync_users',
        checkboxSelector: 'input[name="users[]"]:checked',
        syncTypeKey: 'user',
        ajaxAction: 'wkwctobag_selected_users_sync',
        ajaxDataKey: 'users_data',
        modalTableHeaders: ['User Details', 'Sync As', 'Bagisto Role / Group', 'Status', 'Gender', 'Update Existing'],
        rowParser: (tr, id) => {
            const username = tr.querySelector('.username strong a')?.textContent ||
                tr.querySelector('.username strong')?.textContent ||
                tr.querySelector('.username a')?.textContent ||
                tr.querySelector('.username')?.textContent || 'User #' + id;
            const email = tr.querySelector('.email a')?.textContent || tr.querySelector('.email')?.textContent || '';
            const roleEl = tr.querySelector('.column-role') || tr.querySelector('.role');
            const roleText = (roleEl?.textContent || '').trim().toLowerCase();
            const isDefaultAdmin = roleText.includes('administrator') || roleText.includes('admin');
            return { id, username, email, isDefaultAdmin };
        },
        rowRenderer: (user) => {
            const syncAs = user.isDefaultAdmin ? 'user' : 'customer';
            const defaultStatus = syncAs === 'user' ? wkwctobagAdminObj.default_user_status : wkwctobagAdminObj.default_customer_status;

            let rolesOptions = '';
            const roles = wkwctobagAdminObj.bagisto_roles || [];
            roles.forEach(r => {
                const selected = parseInt(r.id) === parseInt(wkwctobagAdminObj.default_user_role) ? 'selected' : '';
                rolesOptions += `<option value="${r.id}" ${selected}>${r.name}</option>`;
            });
            if (roles.length === 0) {
                rolesOptions = `<option value="">No roles found</option>`;
            }

            let groupsOptions = '';
            const groups = wkwctobagAdminObj.bagisto_groups || [];
            groups.forEach(g => {
                const selected = parseInt(g.id) === parseInt(wkwctobagAdminObj.default_customer_group) ? 'selected' : '';
                groupsOptions += `<option value="${g.id}" ${selected}>${g.name}</option>`;
            });
            if (groups.length === 0) {
                groupsOptions = `<option value="">No groups found</option>`;
            }

            return `
                <tr class="wkwctobag-user-row" data-user-id="${user.id}">
                    <td class="col-user-info">
                        <div class="user-meta-name">${user.username}</div>
                        <div class="user-meta-email">${user.email}</div>
                    </td>
                    <td class="col-sync-as">
                        <select class="row-sync-as wkwctobag-ui-select">
                            <option value="user" ${syncAs === 'user' ? 'selected' : ''}>User</option>
                            <option value="customer" ${syncAs === 'customer' ? 'selected' : ''}>Customer</option>
                        </select>
                    </td>
                    <td class="col-role-group">
                        <div class="field-user-role" style="${syncAs === 'user' ? '' : 'display:none;'}">
                            <select class="row-user-role wkwctobag-ui-select">
                                ${rolesOptions}
                            </select>
                        </div>
                        <div class="field-customer-group" style="${syncAs === 'customer' ? '' : 'display:none;'}">
                            <select class="row-customer-group wkwctobag-ui-select">
                                ${groupsOptions}
                            </select>
                        </div>
                    </td>
                    <td class="col-status">
                        <select class="row-user-status wkwctobag-ui-select">
                            <option value="active" ${defaultStatus === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${defaultStatus === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </td>
                    <td class="col-gender" style="${syncAs === 'user' ? 'text-align: center;' : ''}">
                        <div class="field-customer-gender" style="${syncAs === 'customer' ? '' : 'display:none;'}">
                            <select class="row-customer-gender wkwctobag-ui-select">
                                <option value="male" ${wkwctobagAdminObj.default_customer_gender === 'male' ? 'selected' : ''}>Male</option>
                                <option value="female" ${wkwctobagAdminObj.default_customer_gender === 'female' ? 'selected' : ''}>Female</option>
                                <option value="other" ${wkwctobagAdminObj.default_customer_gender === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <span class="gender-na row-na-text" style="${syncAs === 'user' ? 'margin:0;' : 'display:none;'}">—</span>
                    </td>
                    <td class="col-update-existing">
                        <select class="row-update-existing wkwctobag-ui-select">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </td>
                </tr>
            `;
        },
        onModalRendered: (modalBody) => {
            modalBody.querySelectorAll('.row-sync-as').forEach(select => {
                select.addEventListener('change', function () {
                    const row = this.closest('tr');
                    const syncAs = this.value;

                    const roleField = row.querySelector('.field-user-role');
                    const groupField = row.querySelector('.field-customer-group');
                    const genderField = row.querySelector('.field-customer-gender');
                    const genderNa = row.querySelector('.gender-na');

                    if (syncAs === 'user') {
                        roleField.style.display = '';
                        groupField.style.display = 'none';
                        genderField.style.display = 'none';
                        genderNa.style.display = '';
                    } else {
                        roleField.style.display = 'none';
                        groupField.style.display = '';
                        genderField.style.display = '';
                        genderNa.style.display = 'none';
                    }
                });
            });
        },
        dataCollector: (row) => {
            const userId = row.dataset.userId;
            const syncAs = row.querySelector('.row-sync-as').value;
            const role = row.querySelector('.row-user-role').value;
            const status = row.querySelector('.row-user-status').value;
            const group = row.querySelector('.row-customer-group').value;
            const gender = row.querySelector('.row-customer-gender').value;
            const updateExisting = row.querySelector('.row-update-existing').value;

            return {
                user_id: userId,
                sync_as: syncAs,
                role: role,
                status: status,
                group: group,
                gender: gender,
                update_existing: updateExisting
            };
        }
    });

    // Instantiate Categories Sync Modal
    new WkwcBagSelectiveSyncModal({
        modalId: 'wkwctobag-category-sync-modal',
        bulkActionValue: 'wkwctobag_bulk_sync_categories',
        checkboxSelector: 'input[name="delete_tags[]"]:checked',
        syncTypeKey: 'category',
        ajaxAction: 'wkwctobag_selected_categories_sync',
        ajaxDataKey: 'categories_data',
        modalTableHeaders: ['Category Name', 'Map to Bagisto Category', 'Update Existing'],
        rowParser: (tr, id) => {
            const name = tr.querySelector('.column-name strong a')?.textContent ||
                tr.querySelector('.column-name a')?.textContent ||
                tr.querySelector('.name strong a')?.textContent ||
                tr.querySelector('.name a')?.textContent ||
                tr.querySelector('.column-name')?.textContent ||
                'Category #' + id;
            const slug = (tr.querySelector('.column-slug')?.textContent || tr.querySelector('.slug')?.textContent || '').trim();
            return { id, name, slug };
        },
        rowRenderer: (cat) => {
            const bagistoCategories = wkwctobagAdminObj.bagisto_categories || [];
            const mappedId = wkwctobagAdminObj.category_mappings ? wkwctobagAdminObj.category_mappings[cat.id] : null;

            let matchedCategory = null;
            if (mappedId) {
                matchedCategory = bagistoCategories.find(bc => parseInt(bc.id) === parseInt(mappedId));
            }
            if (!matchedCategory) {
                matchedCategory = bagistoCategories.find(bc =>
                    bc.name.toLowerCase().trim() === cat.name.toLowerCase().trim() ||
                    bc.slug.toLowerCase().trim() === cat.slug.toLowerCase().trim()
                );
            }

            const selectedId = matchedCategory ? matchedCategory.id : -1;

            let categoriesOptions = `<option value="-1" ${selectedId === -1 ? 'selected' : ''}>Create New Category</option>`;
            bagistoCategories.forEach(bc => {
                const selected = parseInt(bc.id) === parseInt(selectedId) ? 'selected' : '';
                categoriesOptions += `<option value="${bc.id}" ${selected}>${bc.name}</option>`;
            });

            return `
                <tr class="wkwctobag-category-row" data-category-id="${cat.id}">
                    <td class="col-user-info">
                        <div class="user-meta-name">${cat.name}</div>
                        <div class="user-meta-email">slug - <code>${cat.slug}</code></div>
                    </td>
                    <td class="col-mapped-category">
                        <select class="row-mapped-category wkwctobag-ui-select">
                            ${categoriesOptions}
                        </select>
                    </td>
                    <td class="col-update-existing">
                        <select class="row-update-existing wkwctobag-ui-select">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </td>
                </tr>
            `;
        },
        dataCollector: (row) => {
            const categoryId = row.dataset.categoryId;
            const mappedCategoryId = row.querySelector('.row-mapped-category').value;
            const updateExisting = row.querySelector('.row-update-existing').value;

            return {
                category_id: categoryId,
                mapped_category_id: mappedCategoryId,
                update_existing: updateExisting
            };
        }
    });

    // Instantiate Products Sync Modal
    new WkwcBagSelectiveSyncModal({
        modalId: 'wkwctobag-product-sync-modal',
        bulkActionValue: 'wkwctobag_bulk_sync_products',
        checkboxSelector: 'input[name="post[]"]:checked',
        syncTypeKey: 'product',
        ajaxAction: 'wkwctobag_selected_products_sync',
        ajaxDataKey: 'products_data',
        modalTableHeaders: ['Product Details', 'SKU', 'Update Existing'],
        rowParser: (tr, id) => {
            const name = tr.querySelector('.row-title')?.textContent ||
                tr.querySelector('.column-name strong a')?.textContent ||
                tr.querySelector('.name strong a')?.textContent ||
                tr.querySelector('.column-name')?.textContent ||
                'Product #' + id;
            const sku = (tr.querySelector('.column-sku')?.textContent || tr.querySelector('.sku')?.textContent || '').trim() || '—';
            return { id, name, sku };
        },
        rowRenderer: (prod) => {
            return `
                <tr class="wkwctobag-product-row" data-product-id="${prod.id}">
                    <td class="col-user-info">
                        <div class="user-meta-name">${prod.name}</div>
                    </td>
                    <td class="col-sku">
                        <code>${prod.sku}</code>
                    </td>
                    <td class="col-update-existing">
                        <select class="row-update-existing wkwctobag-ui-select">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </td>
                </tr>
            `;
        },
        dataCollector: (row) => {
            const productId = row.dataset.productId;
            const updateExisting = row.querySelector('.row-update-existing').value;

            return {
                product_id: productId,
                update_existing: updateExisting
            };
        }
    });

    // Instantiate Attributes Sync Modal
    new WkwcBagSelectiveSyncModal({
        modalId: 'wkwctobag-attribute-sync-modal',
        bulkActionValue: 'wkwctobag_bulk_sync_attributes',
        checkboxSelector: 'input[name="wkwctobag_attribute_ids[]"]:checked',
        syncTypeKey: 'attribute',
        ajaxAction: 'wkwctobag_selected_attributes_sync',
        ajaxDataKey: 'attributes_data',
        applyBtnSelector: '#wkwctobag-attribute-bulk-apply',
        actionSelectSelector: '#wkwctobag-attribute-bulk-action',
        modalTableHeaders: ['Attribute Name', 'Slug', 'Update Existing'],
        rowParser: (tr, id) => {
            const nameEl = tr.querySelector('.column-name strong a') ||
                tr.querySelector('.column-name a') ||
                tr.querySelector('strong a') ||
                tr.querySelector('td:nth-child(2)');
            const name = nameEl ? nameEl.textContent : 'Attribute #' + id;

            const slugEl = tr.querySelector('.column-slug') ||
                tr.querySelector('.slug') ||
                tr.querySelector('td:nth-child(3)');
            const slug = slugEl ? slugEl.textContent : '';

            return { id, name: name.trim(), slug: slug.trim() };
        },
        rowRenderer: (attr) => {
            return `
                <tr class="wkwctobag-attribute-row" data-attribute-id="${attr.id}">
                    <td class="col-user-info">
                        <div class="user-meta-name">${attr.name}</div>
                    </td>
                    <td class="col-slug">
                        <code>${attr.slug}</code>
                    </td>
                    <td class="col-update-existing">
                        <select class="row-update-existing wkwctobag-ui-select">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </td>
                </tr>
            `;
        },
        dataCollector: (row) => {
            const attributeId = row.dataset.attributeId;
            const updateExisting = row.querySelector('.row-update-existing').value;

            return {
                attribute_id: attributeId,
                update_existing: updateExisting
            };
        }
    });

    // Instantiate Orders Sync Modal
    new WkwcBagSelectiveSyncModal({
        modalId: 'wkwctobag-order-sync-modal',
        bulkActionValue: 'wkwctobag_bulk_sync_orders',
        checkboxSelector: 'input[name="post[]"]:checked, input[name="order_id[]"]:checked, input[name="id[]"]:checked',
        syncTypeKey: 'order',
        ajaxAction: 'wkwctobag_selected_orders_sync',
        ajaxDataKey: 'orders_data',
        modalTableHeaders: ['Order Details', 'Customer', 'Total', 'Update Existing'],
        rowParser: (tr, id) => {
            const orderNumberEl = tr.querySelector('.order_number a strong') ||
                tr.querySelector('.column-order_number strong a') ||
                tr.querySelector('.column-order_number a') ||
                tr.querySelector('.row-title') ||
                tr.querySelector('.order-number a');
            const name = (orderNumberEl?.textContent || '').trim() || 'Order #' + id;

            const customerEl = tr.querySelector('.customer_user') ||
                tr.querySelector('.column-customer') ||
                tr.querySelector('.column-customer_user') ||
                tr.querySelector('.column-billing_address') ||
                tr.querySelector('.column-order_number .customer-name');
            let customer = (customerEl?.textContent || '').trim() || 'Guest';
            if (customer && customer !== 'Guest') {
                customer = customer.split(',')[0].trim();
            }

            const totalEl = tr.querySelector('.order_total') ||
                tr.querySelector('.column-order_total') ||
                tr.querySelector('.amount');
            const total = (totalEl?.textContent || '').trim() || '—';

            return { id, name, customer, total };
        },
        rowRenderer: (ord) => {
            return `
                <tr class="wkwctobag-order-row" data-order-id="${ord.id}">
                    <td class="col-user-info">
                        <div class="user-meta-name">${ord.name}</div>
                    </td>
                    <td class="col-customer">
                        <code>${ord.customer}</code>
                    </td>
                    <td class="col-total">
                        <code>${ord.total}</code>
                    </td>
                    <td class="col-update-existing">
                        <select class="row-update-existing wkwctobag-ui-select">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                    </td>
                </tr>
            `;
        },
        dataCollector: (row) => {
            const orderId = row.dataset.orderId;
            const updateExisting = row.querySelector('.row-update-existing').value;

            return {
                order_id: orderId,
                update_existing: updateExisting
            };
        }
    });
});
