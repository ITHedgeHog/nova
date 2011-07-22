<?php echo text_output($header, 'h1', 'page-head');?>

<?php if (isset($missions) && $missions === FALSE): ?>
	<?php echo text_output($label['no_mission'], 'p', 'bold');?>
<?php else: ?>
	<?php if ($this->options['use_mission_notes'] == 'y'): ?>
		<div id="notes">
			<p class="float_right fontSmall">
				<a href="#" id="toggle_notes"><strong><?php echo $label['showhide'];?></strong></a>
			</p>
			<?php echo text_output($label['mission_notes'], 'h3');?>
			<div class="notes_content hidden">
				<?php if (isset($mission)): ?>
					<?php echo text_output($mission['notes']);?>
				<?php elseif (isset($mission_notes)): ?>
					<?php foreach ($mission_notes as $m): ?>
						<?php echo text_output($m['title'], 'p', 'bold');?>
						<?php echo text_output($m['notes']);?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php echo form_open($form_action);?>
		<?php if (isset($all_characters) and is_array($all_characters)): ?>
			<p>
				<kbd><?php echo $label['authors'];?></kbd>
				<?php echo form_multiselect('authors[]', $all_characters, $authors_selected, 'id="all" class="chosen" title="'.$label['select'].'"');?>
			</p>
		<?php endif;?>
		
		<p>
			<kbd><?php echo $label['mission'];?></kbd>
			<?php if (isset($missions)): ?>
				<?php echo form_dropdown('mission', $missions, $inputs['mission'], 'class="chosen"');?>
			<?php else: ?>
				<?php echo anchor('sim/missions/id/'. $mission['id'], $mission['title']); ?>
				<?php echo form_hidden('mission', $mission['id']);?>
			<?php endif; ?>
		</p>
		
		<p>
			<kbd><?php echo $label['title'];?></kbd>
			<?php echo form_input($inputs['title']);?>
		</p>
		
		<p>
			<kbd><?php echo $label['location'];?></kbd>
			<?php echo form_input($inputs['location']);?>
		</p>
		
		<p>
			<kbd><?php echo $label['timeline'];?></kbd>
			<?php echo form_input($inputs['timeline']);?>
		</p>
		
		<p>
			<kbd><?php echo $label['content'];?></kbd>
			<?php echo form_textarea($inputs['content']);?>
		</p>
		
		<p>
			<kbd><?php echo $label['tags'];?></kbd>
			<?php echo text_output($label['tags_sep'], 'span', 'fontSmall gray bold');?><br />
			<?php echo form_input($inputs['tags']);?>
		</p><br />
		
		<p>
			<?php echo form_button($inputs['post']);?>
			&nbsp;
			<?php echo form_button($inputs['save']);?>
		
			<?php if ($this->uri->segment(3) !== false): ?>
				&nbsp;
				<?php echo form_button($inputs['delete']);?>
			<?php endif; ?>
		</p>
	<?php echo form_close();?>
<?php endif;?>