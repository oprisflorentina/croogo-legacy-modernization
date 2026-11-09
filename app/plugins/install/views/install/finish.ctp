<div class="install">
	<h2><?php echo $title_for_layout; ?></h2>

	<p>
		Admin panel: <?php echo $this->Html->link(Router::url('/admin', true), Router::url('/admin', true)); ?><br />
		Username: admin<br />
		<?php if (isset($adminPassword)): ?>
			Password: <strong><?php echo h($adminPassword); ?></strong><br />
			Write it down now: it is shown only on this page. You can change it under Users.
		<?php endif; ?>
	</p>

	<br />
	<br />

	<p>
		Delete the installation directory <strong>/app/plugins/install</strong>.
	</p>

	<br />
	<br />

	<?php
		echo $this->Html->link(__('Click here to delete installation files', true), array(
			'plugin' => 'install',
			'controller' => 'install',
			'action' => 'finish',
			'delete' => 1,
		));
	?>
</div>