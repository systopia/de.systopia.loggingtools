{*-------------------------------------------------------+
| SYSTOPIA LOGGING TOOLS EXTENSION                       |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{crmScope extensionKey='de.systopia.loggingtools'}

<h3>{ts}Logging Tools Truncation{/ts}</h3>

<p class="red warning">
  {ts}<b>WARNING</b>: This process could potentially damage your logging data. Be sure to have a backup before proceeding.{/ts}
</p>

<p>
    {ts}You can choose a time horizon for since when the logging table entries shall be kept. All entries older than the given time frame (or point in time) will be deleted.{/ts}
</p>

<br>

<div class="crm-section">
    <div class="label">{$form.time_horizon.label}</div>
    <div class="content">{$form.time_horizon.html}</div>
    <div class="clear"></div>
</div>
<div class="crm-section custom-time-horizon" style="display: none;">
    <div class="label">{$form.custom_time_horizon.label}</div>
    <div class="content">{$form.custom_time_horizon.html}</div>
    <div class="clear"></div>
</div>
<div class="crm-section">
    <div class="label">{$form.logging_tables.label}</div>
    <div class="content">{$form.logging_tables.html}</div>
    <div class="clear"></div>
</div>

<br>

<p class="red warning">
    {ts}<b>WARNING</b>: This process must not be halted until full completion. Note that logging has to be disabled while truncating, meaning that any changes during the process will not be recorded.{/ts}
</p>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
<script>
  cj(document).ready(function() {
    cj("select[name=time_horizon]").change(function() {
      let current_value = cj("select[name=time_horizon]").val();
      if (current_value === 'custom') {
        cj("div.custom-time-horizon").show();
      } else {
        cj("div.custom-time-horizon").hide();
      }
    });
  });
</script>
{/literal}
{/crmScope}

