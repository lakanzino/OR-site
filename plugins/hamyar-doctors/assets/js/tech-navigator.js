/**
 * File: wp-content/plugins/hamyar-doctors/assets/js/tech-navigator.js
 * Description: Interactive State Machine for Dual-Funnel Surgeon Discovery.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        const hub = document.getElementById('hj-surgeon-finder');
        if (!hub || typeof HJ_NAV_CONFIG === 'undefined') return;

        const modeBtns = hub.querySelectorAll('.hj-mode-btn');
        const panelLocationRoot = document.getElementById('hj-panel-location-root');
        const panelSpecialtyRoot = document.getElementById('hj-panel-specialty-root');
        const dynamicStage = document.getElementById('hj-dynamic-stage');
        const breadcrumbBar = document.getElementById('hj-nav-breadcrumb');
        const breadcrumbTrail = document.getElementById('hj-breadcrumb-trail');
        const backBtn = document.getElementById('hj-back-step-btn');
        const provFilterInput = document.getElementById('hj-province-quick-filter');

        let currentMode = 'location';
        let historyStack = [];

        modeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentMode = btn.getAttribute('data-mode');
                modeBtns.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                resetToRoot();
            });
        });

        function resetToRoot() {
            historyStack = [];
            breadcrumbBar.style.display = 'none';
            dynamicStage.style.display = 'none';
            dynamicStage.innerHTML = '';

            if (currentMode === 'location') {
                panelLocationRoot.classList.add('is-active');
                panelSpecialtyRoot.classList.remove('is-active');
            } else {
                panelSpecialtyRoot.classList.add('is-active');
                panelLocationRoot.classList.remove('is-active');
            }
        }

        if (provFilterInput) {
            provFilterInput.addEventListener('input', function () {
                const q = this.value.trim();
                const pills = document.querySelectorAll('#hj-provinces-grid-main .hj-province-pill');
                pills.forEach(function (pill) {
                    const name = pill.getAttribute('data-province-name') || '';
                    pill.style.display = name.indexOf(q) !== -1 ? 'flex' : 'none';
                });
            });
        }

        hub.addEventListener('click', function (e) {
            const provBtn = e.target.closest('.hj-province-pill');
            if (provBtn) {
                const provId = provBtn.getAttribute('data-province-id');
                const provName = provBtn.getAttribute('data-province-name');
                const specId = provBtn.getAttribute('data-specialty-id') || 0;

                loadDynamicStage({
                    step: 'load_cities',
                    province_id: provId,
                    specialty_id: specId,
                    trailText: 'استان ' + provName + ' ← انتخاب شهرستان'
                });
                return;
            }

            const cityBtn = e.target.closest('.hj-city-btn');
            if (cityBtn) {
                const cityId = cityBtn.getAttribute('data-city-id');
                const cityName = cityBtn.getAttribute('data-city-name');
                const specId = cityBtn.getAttribute('data-specialty-id') || 0;

                loadDynamicStage({
                    step: 'load_city_surgeons',
                    city_id: cityId,
                    specialty_id: specId,
                    trailText: 'شهرستان ' + cityName + ' ← فهرست تخصص‌ها و جراحان'
                });
                return;
            }

            const specBtn = e.target.closest('.hj-specialty-card');
            if (specBtn) {
                const specId = specBtn.getAttribute('data-specialty-id');
                const specName = specBtn.getAttribute('data-specialty-name');

                loadDynamicStage({
                    step: 'load_specialty_showcase',
                    specialty_id: specId,
                    trailText: 'تخصص: ' + specName + ' (ویترین جراحان برتر + انتخاب استان)'
                });
                return;
            }
        });

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                if (historyStack.length <= 1) {
                    resetToRoot();
                    return;
                }
                historyStack.pop();
                const prevState = historyStack.pop();
                if (prevState) {
                    loadDynamicStage(prevState);
                } else {
                    resetToRoot();
                }
            });
        }

        function loadDynamicStage(params) {
            historyStack.push(params);

            panelLocationRoot.classList.remove('is-active');
            panelSpecialtyRoot.classList.remove('is-active');
            breadcrumbBar.style.display = 'flex';
            breadcrumbTrail.textContent = params.trailText || '';

            dynamicStage.style.display = 'block';
            dynamicStage.innerHTML = '<div style="text-align:center;padding:40px;color:#0284c7;font-weight:bold;">⚡ در حال بارگذاری اطلاعات طبقه‌بندی‌شده...</div>';

            const formData = new FormData();
            formData.append('action', 'hj_nav_drilldown');
            formData.append('nonce', HJ_NAV_CONFIG.nonce);
            formData.append('step', params.step);
            if (params.province_id) formData.append('province_id', params.province_id);
            if (params.city_id) formData.append('city_id', params.city_id);
            if (params.specialty_id) formData.append('specialty_id', params.specialty_id);

            fetch(HJ_NAV_CONFIG.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.html) {
                        dynamicStage.innerHTML = res.data.html;
                        hub.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        dynamicStage.innerHTML = '<p style="color:#dc2626;padding:20px;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>';
                    }
                })
                .catch(() => {
                    dynamicStage.innerHTML = '<p style="color:#dc2626;padding:20px;">خطای ارتباط با سرور.</p>';
                });
        }

    });

    window.hjScrollToProvinceFunnel = function (e) {
        e.preventDefault();
        const funnel = document.querySelector('.hj-specialty-province-funnel');
        if (funnel) {
            funnel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

})();