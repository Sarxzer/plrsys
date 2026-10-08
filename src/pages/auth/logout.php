<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
Guards::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$auth->logout();
	Alert::success(__('logout.success'));
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= __('logout.page_title') ?></title>
	<link rel="stylesheet" href="<?= $cssDir ?>">
	<link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
	<script src="<?= $jsDir ?>" defer></script>
</head>

<body>
	<div class="page">
		<div class="pixel-scanlines"></div>
		<div class="content">
			<?php include $includesDir . '/navbar.php'; ?>
			<div class="alerts-wrapper">
				<?php include $includesDir . '/alerts.php'; ?>
			</div>
			<div class="main">
				<div class="login-container">
					<h1 class="login-title"><?= __('logout.title') ?></h1>
					<form action="/logout" method="post" class="login-form">
						<input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
						<input type="submit" value="<?= __('logout.submit') ?>">
					</form>
					<p class="login-subtext"><a href="/settings"><?= __('logout.cancel') ?></a></p>
				</div>
			</div>

			<?php include $includesDir . '/footer.php'; ?>
		</div>
	</div>
</body>

</html>