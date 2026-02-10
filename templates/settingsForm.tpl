<script>
	$(function() {ldelim}
		$('#registrationFieldsSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		function toggleRequire(enableId, requireId) {ldelim}
			var $enable = $('#' + enableId);
			var $require = $('#' + requireId);
			function update() {ldelim}
				if ($enable.is(':checked')) {ldelim}
					$require.prop('disabled', false);
				{rdelim} else {ldelim}
					$require.prop('disabled', true).prop('checked', false);
				{rdelim}
			{rdelim}
			$enable.on('change', update);
			update();
		{rdelim}

		toggleRequire('enableOrcid', 'requireOrcid');
		toggleRequire('enablePhone', 'requirePhone');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="registrationFieldsSettings"
	method="POST"
	action="{$saveUrl|escape}"
>
	{csrf}

	{fbvFormArea id="registrationFieldsSettingsArea" title="plugins.generic.registrationFields.settings.title"}

		{fbvFormSection title="plugins.generic.registrationFields.settings.orcid" list="true"}
			{fbvElement
				type="checkbox"
				id="enableOrcid"
				label="plugins.generic.registrationFields.settings.enableOrcid"
				checked=$enableOrcid
			}
			{fbvElement
				type="checkbox"
				id="requireOrcid"
				label="plugins.generic.registrationFields.settings.requireOrcid"
				checked=$requireOrcid
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.registrationFields.settings.phone" list="true"}
			{fbvElement
				type="checkbox"
				id="enablePhone"
				label="plugins.generic.registrationFields.settings.enablePhone"
				checked=$enablePhone
			}
			{fbvElement
				type="checkbox"
				id="requirePhone"
				label="plugins.generic.registrationFields.settings.requirePhone"
				checked=$requirePhone
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.registrationFields.settings.debug" list="true"}
			{fbvElement
				type="checkbox"
				id="debugMode"
				label="plugins.generic.registrationFields.settings.debugMode"
				checked=$debugMode
			}
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
