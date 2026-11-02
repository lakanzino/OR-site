/**
 * File: assets/js/tech-navigator.js
 * Description: Interactive state machine for the dual-funnel surgeon navigator.
 *
 * نسخهٔ ۰.۳.۱: پشتهٔ تاریخچه اصلاح شد (دکمهٔ بازگشت دیگر وضعیت تکراری
 * نمی‌سازد)، درخواست‌های هم‌زمان لغو می‌شوند، مسیر راهنما (breadcrumb)
 * کلیک‌پذیر است و حالت بارگذاری/خطا یکدست نمایش داده می‌شود.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var hub = document.getElementById('hj-surgeon-finder');
        if (!hub || typeof HJ_NAV_CONFIG === 'undefined') {
            return;
        }

        var modeBtns = hub.querySelectorAll('.hj-mode-btn');
        var panelLocationRoot = document.getElementById('hj-panel-location-root');
        var panelSpecialtyRoot = document.getElementById('hj-panel-specialty-root');
        var dynamicStage = document.getElementById('hj-dynamic-stage');
        var breadcrumbBar = document.getElementById('hj-nav-breadcrumb');
        var breadcrumbTrail = document.getElementById('hj-breadcrumb-trail');
        var backBtn = document.getElementById('hj-back-step-btn');
        var provFilterInput = document.getElementById('hj-province-quick-filter');

        var currentMode = 'location';
        var stack = [];        // وضعیت‌های پیموده‌شده؛ آخرین عضو، وضعیت جاری است.
        var inFlight = null;   // AbortController درخواست جاری.
        var requestId = 0;

        /* ---------------------------------------------------------------
         * نمایش ریشهٔ هر حالت
         * ------------------------------------------------------------- */

        function applyModeUI() {
            if (!panelLocationRoot || !panelSpecialtyRoot) {
                return;
            }

            var isLocation = currentMode === 'location';
            panelLocationRoot.classList.toggle('is-active', isLocation);
            panelSpecialtyRoot.classList.toggle('is-active', !isLocation);
        }

        function resetToRoot() {
            abortInFlight();
            stack = [];

            if (breadcrumbBar) {
                breadcrumbBar.style.display = 'none';
            }
            if (dynamicStage) {
                dynamicStage.style.display = 'none';
                dynamicStage.innerHTML = '';
                dynamicStage.removeAttribute('aria-busy');
            }
            if (backBtn) {
                backBtn.hidden = true;
            }

            applyModeUI();
            syncFilterWithMode();
        }

        function syncFilterWithMode() {
            if (!provFilterInput) {
                return;
            }
            provFilterInput.value = '';
            hub.querySelectorAll('#hj-provinces-grid-main .hj-province-pill').forEach(function (pill) {
                pill.style.display = '';
            });
        }

        /* ---------------------------------------------------------------
         * مسیر راهنما
         * ------------------------------------------------------------- */

        function renderBreadcrumb() {
            if (!breadcrumbBar || !breadcrumbTrail) {
                return;
            }

            breadcrumbTrail.innerHTML = '';

            if (stack.length === 0) {
                breadcrumbBar.style.display = 'none';
                return;
            }

            breadcrumbBar.style.display = 'flex';

            stack.forEach(function (state, index) {
                if (index > 0) {
                    var sep = document.createElement('span');
                    sep.className = 'hj-crumb-sep';
                    sep.textContent = '‹';
                    breadcrumbTrail.appendChild(sep);
                }

                var isCurrent = (index === stack.length - 1);
                var el = document.createElement(isCurrent ? 'span' : 'button');
                el.className = 'hj-crumb' + (isCurrent ? ' is-current' : '');
                el.textContent = state.label || '';

                if (!isCurrent) {
                    el.type = 'button';
                    el.setAttribute('data-crumb-index', String(index));
                }

                breadcrumbTrail.appendChild(el);
            });

            if (backBtn) {
                backBtn.hidden = stack.length < 1;
            }
        }

        /* ---------------------------------------------------------------
         * فراخوانی AJAX
         * ------------------------------------------------------------- */

        function abortInFlight() {
            if (inFlight) {
                inFlight.abort();
                inFlight = null;
            }
        }

        function showLoading() {
            if (!dynamicStage) {
                return;
            }
            dynamicStage.style.display = 'block';
            dynamicStage.setAttribute('aria-busy', 'true');
            dynamicStage.innerHTML = '<div class="hj-nav-loading"><span class="hj-nav-loading__dot"></span> در حال بارگذاری اطلاعات طبقه‌بندی‌شده…</div>';
        }

        function showError(message) {
            if (!dynamicStage) {
                return;
            }
            dynamicStage.removeAttribute('aria-busy');

            var box = document.createElement('div');
            box.className = 'hj-nav-error';

            var text = document.createElement('p');
            text.textContent = message || 'خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.';
            box.appendChild(text);

            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'hj-nav-retry';
            retry.textContent = 'تلاش دوباره';
            retry.addEventListener('click', function () {
                if (stack.length > 0) {
                    loadState(stack[stack.length - 1], false);
                }
            });
            box.appendChild(retry);

            dynamicStage.innerHTML = '';
            dynamicStage.appendChild(box);
        }

        /**
         * بارگذاری یک وضعیت از سرور.
         *
         * @param {Object}  state   {step, province_id, city_id, specialty_id, label}
         * @param {boolean} pushToStack آیا وضعیت به پشته اضافه شود.
         */
        function loadState(state, pushToStack) {
            if (!dynamicStage || !state) {
                return;
            }

            abortInFlight();
            showLoading();

            var controller = new AbortController();
            inFlight = controller;

            var myRequest = ++requestId;
            var formData = new FormData();
            formData.append('action', 'hj_nav_drilldown');
            formData.append('nonce', HJ_NAV_CONFIG.nonce);
            formData.append('step', state.step);

            if (state.province_id) {
                formData.append('province_id', state.province_id);
            }
            if (state.city_id) {
                formData.append('city_id', state.city_id);
            }
            if (state.specialty_id) {
                formData.append('specialty_id', state.specialty_id);
            }

            fetch(HJ_NAV_CONFIG.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (res) {
                    if (myRequest !== requestId) {
                        return; // پاسخ کهنه؛ نادیده گرفته می‌شود.
                    }

                    inFlight = null;

                    if (res && res.success && res.data && typeof res.data.html === 'string') {
                        if (pushToStack !== false) {
                            stack.push(state);
                        }
                        dynamicStage.innerHTML = res.data.html;
                        dynamicStage.removeAttribute('aria-busy');
                        renderBreadcrumb();
                        smoothScrollTo(hub);
                    } else {
                        showError(res && res.data && res.data.message ? res.data.message : null);
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    showError('خطای ارتباط با سرور.');
                });
        }

        function smoothScrollTo(el) {
            if (!el || typeof el.scrollIntoView !== 'function') {
                return;
            }
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {
                el.scrollIntoView();
            }
        }

        /* ---------------------------------------------------------------
         * رویدادها
         * ------------------------------------------------------------- */

        modeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-mode');
                if (mode === currentMode) {
                    return;
                }

                currentMode = mode;
                modeBtns.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                resetToRoot();
            });
        });

        if (provFilterInput) {
            provFilterInput.addEventListener('input', function () {
                var q = this.value.trim();
                hub.querySelectorAll('#hj-provinces-grid-main .hj-province-pill').forEach(function (pill) {
                    var name = pill.getAttribute('data-province-name') || '';
                    pill.style.display = (q === '' || name.indexOf(q) !== -1) ? 'flex' : 'none';
                });
            });
        }

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                if (stack.length === 0) {
                    resetToRoot();
                    return;
                }

                // وضعیت جاری را دور می‌ریزیم و وضعیت قبلی را دوباره می‌خوانیم.
                stack.pop();

                if (stack.length === 0) {
                    resetToRoot();
                    return;
                }

                var previous = stack[stack.length - 1];
                stack.pop(); // loadState خودش دوباره push می‌کند؛ از تکرار جلوگیری می‌شود.
                loadState(previous, true);
            });
        }

        hub.addEventListener('click', function (e) {
            var crumb = e.target.closest('[data-crumb-index]');
            if (crumb) {
                var index = parseInt(crumb.getAttribute('data-crumb-index'), 10);
                if (!isNaN(index) && index >= 0 && index < stack.length) {
                    var target = stack[index];
                    stack = stack.slice(0, index);
                    loadState(target, true);
                }
                return;
            }

            var provBtn = e.target.closest('.hj-province-pill');
            if (provBtn) {
                loadState({
                    step: 'load_cities',
                    province_id: provBtn.getAttribute('data-province-id'),
                    specialty_id: provBtn.getAttribute('data-specialty-id') || 0,
                    label: 'استان ' + (provBtn.getAttribute('data-province-name') || '')
                });
                return;
            }

            var cityBtn = e.target.closest('.hj-city-btn');
            if (cityBtn) {
                loadState({
                    step: 'load_city_surgeons',
                    city_id: cityBtn.getAttribute('data-city-id'),
                    specialty_id: cityBtn.getAttribute('data-specialty-id') || 0,
                    label: 'شهرستان ' + (cityBtn.getAttribute('data-city-name') || '')
                });
                return;
            }

            var specBtn = e.target.closest('.hj-specialty-card');
            if (specBtn) {
                loadState({
                    step: 'load_specialty_showcase',
                    specialty_id: specBtn.getAttribute('data-specialty-id'),
                    label: 'تخصص ' + (specBtn.getAttribute('data-specialty-name') || '')
                });
            }
        });

        /* ---------------------------------------------------------------
         * اسکرول به قیف انتخاب استان (کارت‌های بلورین)
         * ------------------------------------------------------------- */

        window.hjScrollToProvinceFunnel = function (e) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            var funnel = document.querySelector('.hj-specialty-province-funnel');
            if (funnel) {
                smoothScrollTo(funnel);
            }
        };

        // وضعیت اولیه.
        resetToRoot();
    });

})();
