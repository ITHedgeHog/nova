<?php echo text_output($label['text'], 'p', 'fontMedium');?>

<?php echo form_open('install/changedb/verify');?>
	<p>
		<kbd><?php echo $label['email'];?></kbd>
		<?php echo form_input($inputs['email']);?>
	</p>
	<p>
		<kbd><?php echo $label['password'];?></kbd>
		<?php echo form_password($inputs['password']);?>
	</p>