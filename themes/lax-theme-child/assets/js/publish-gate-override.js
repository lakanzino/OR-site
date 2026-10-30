/**
 * Publish-gate override: a sidebar checkbox in the block editor.
 *
 * Administrators can tick it to publish an article even when the gate reports
 * a failing check. The value is saved as post meta (_hj_gate_override) through
 * REST, which the PHP side reads in inc/publish-gate.php.
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.plugins || !wp.editPost || !wp.data || !wp.components || !wp.compose) {
		return;
	}

	var el = wp.element.createElement;
	var CheckboxControl = wp.components.CheckboxControl;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var compose = wp.compose.compose;
	var withSelect = wp.data.withSelect;
	var withDispatch = wp.data.withDispatch;

	var META = '_hj_gate_override';
	var GATED_TYPES = ['surgery', 'patient_guide'];

	function OverridePanel(props) {
		if (GATED_TYPES.indexOf(props.postType) === -1) {
			return null;
		}
		return el(
			PluginDocumentSettingPanel,
			{ name: 'hj-gate-override', title: 'دروازهٔ انتشار', className: 'hj-gate-override' },
			el(CheckboxControl, {
				label: 'انتشار با وجود خطا (فقط مدیر سایت)',
				help: 'اگر دروازه خطا نشان دهد، با این گزینه مقاله با تأیید شما منتشر می‌شود. پیش از فعال کردن، فهرست باکس «دروازهٔ انتشار» را بررسی کنید.',
				checked: !!props.checked,
				onChange: function (value) {
					props.setChecked(!!value);
				}
			})
		);
	}

	var Enhanced = compose(
		withSelect(function (select) {
			var editor = select('core/editor');
			var meta = editor.getEditedPostAttribute('meta') || {};
			return {
				postType: editor.getCurrentPostType(),
				checked: !!meta[META]
			};
		}),
		withDispatch(function (dispatch) {
			return {
				setChecked: function (value) {
					var meta = {};
					meta[META] = value;
					dispatch('core/editor').editPost({ meta: meta });
				}
			};
		})
	)(OverridePanel);

	wp.plugins.registerPlugin('hj-publish-gate-override', { render: Enhanced });
})(window.wp);
