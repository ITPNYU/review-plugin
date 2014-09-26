<div>
<h2>ITP Review Settings</h2>
<form action="options.php" method="post">
<?php settings_fields('rp-options'); ?>
<?php do_settings_sections('rp-options'); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</form>
</div>
