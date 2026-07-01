<?php
session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar'])) {
	$email        = trim($_POST['username'] ?? '');
	$senha_digit  = (string)($_POST['password'] ?? '');
	$tipo_usuario = (string)($_POST['tipo_usuario'] ?? '');

	try {
		$stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE USU_EMAIL = :email LIMIT 1");
		$stmt->execute([':email' => $email]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		$ok = false;
		if ($user) {
			$hash = isset($user['USU_SENHA']) ? rtrim((string)$user['USU_SENHA']) : '';
			if ($hash !== '' && password_verify($senha_digit, $hash)) {
				$ok = true;
			}
		}

		if ($ok) {
			session_regenerate_id(true);
			$_SESSION['SessUsuNome']   = $user['USU_NOME']   ?? '';
			$_SESSION['sessionTitulo'] = 'Valiantus - Associação Veicular';
			$_SESSION['SessUsuEmail']  = $user['USU_EMAIL']  ?? '';
			$_SESSION['SessUsuCodigo'] = $user['USU_CODIGO_PK'] ?? null;
			$_SESSION['SessUsuTipo']   = $user['USU_TIPO']   ?? '';
			$_SESSION['SessLoginTipo'] = $tipo_usuario;

			header('Location: ' . rtrim(APP_URL, '/') . '/index.php');
			exit;
		}

		$_SESSION['msg'] = 'Usuário ou senha incorretos!';
	} catch (Throwable $e) {
		$_SESSION['msg'] = 'Falha ao autenticar. Tente novamente.';
	}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="utf-8" />
	<title>Valiantus — Acesso ao Sistema</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

	<style>
		*,
		*::before,
		*::after {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		:root {
			--accent: #3b5bdb;
			--accent-dark: #2f4abf;
			--accent-light: #e8edff;
			--ink: #1a1d2e;
			--muted: #868e96;
			--border: #dee2e6;
			--surface: #ffffff;
			--bg: #f0f2f8;
			--error: #c92a2a;
			--error-bg: #fff5f5;
			--font: 'Plus Jakarta Sans', sans-serif;
			--radius: 14px;
		}

		html,
		body {
			height: 100%;
			font-family: var(--font);
			background: var(--bg);
			color: var(--ink);
		}

		/* ── Layout dividido ── */
		.login-page {
			display: flex;
			min-height: 100vh;
		}

		/* Painel esquerdo — identidade visual */
		.login-panel {
			flex: 1;
			background: var(--accent);
			background-image:
				radial-gradient(ellipse at 20% 20%, rgba(255, 255, 255, .08) 0%, transparent 60%),
				radial-gradient(ellipse at 80% 80%, rgba(0, 0, 0, .15) 0%, transparent 60%);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 60px 48px;
			position: relative;
			overflow: hidden;
		}

		.login-panel::before {
			content: '';
			position: absolute;
			width: 500px;
			height: 500px;
			border-radius: 50%;
			border: 1px solid rgba(255, 255, 255, .08);
			top: -180px;
			left: -120px;
		}

		.login-panel::after {
			content: '';
			position: absolute;
			width: 320px;
			height: 320px;
			border-radius: 50%;
			border: 1px solid rgba(255, 255, 255, .06);
			bottom: -80px;
			right: -80px;
		}

		.panel-logo {
			width: 130px;
			margin-bottom: 40px;
			filter: drop-shadow(0 4px 20px rgba(0, 0, 0, .2));
			position: relative;
			z-index: 1;
		}

		.panel-title {
			font-size: 30px;
			font-weight: 800;
			color: #fff;
			text-align: center;
			line-height: 1.2;
			margin-bottom: 16px;
			position: relative;
			z-index: 1;
		}

		.panel-sub {
			font-size: 15px;
			color: rgba(255, 255, 255, .7);
			text-align: center;
			max-width: 320px;
			line-height: 1.6;
			position: relative;
			z-index: 1;
		}

		.panel-badges {
			display: flex;
			gap: 10px;
			margin-top: 40px;
			flex-wrap: wrap;
			justify-content: center;
			position: relative;
			z-index: 1;
		}

		.panel-badge {
			background: rgba(255, 255, 255, .12);
			border: 1px solid rgba(255, 255, 255, .18);
			border-radius: 99px;
			padding: 8px 16px;
			font-size: 12.5px;
			font-weight: 500;
			color: rgba(255, 255, 255, .9);
			display: flex;
			align-items: center;
			gap: 6px;
		}

		/* Painel direito — formulário */
		.login-form-wrap {
			width: 460px;
			flex-shrink: 0;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 48px 48px;
			background: var(--surface);
		}

		.form-inner {
			width: 100%;
			max-width: 360px;
		}

		.form-heading {
			margin-bottom: 32px;
		}

		.form-heading h2 {
			font-size: 24px;
			font-weight: 800;
			color: var(--ink);
			margin-bottom: 6px;
		}

		.form-heading p {
			font-size: 14px;
			color: var(--muted);
		}

		/* Alerta de erro */
		.alert-erro {
			background: var(--error-bg);
			border: 1px solid #ffc9c9;
			border-radius: 10px;
			padding: 12px 14px;
			font-size: 13.5px;
			color: var(--error);
			margin-bottom: 20px;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		/* Campo de formulário */
		.field-group {
			margin-bottom: 16px;
		}

		.field-group label {
			display: block;
			font-size: 13px;
			font-weight: 600;
			color: var(--ink);
			margin-bottom: 7px;
		}

		.field-wrap {
			position: relative;
		}

		.field-wrap .field-icon {
			position: absolute;
			left: 14px;
			top: 50%;
			transform: translateY(-50%);
			color: var(--muted);
			font-size: 15px;
			pointer-events: none;
		}

		.field-wrap input,
		.field-wrap select {
			width: 100%;
			height: 48px;
			padding: 0 14px 0 42px;
			border: 1.5px solid var(--border);
			border-radius: 10px;
			font-family: var(--font);
			font-size: 14px;
			color: var(--ink);
			background: #fafbfc;
			outline: none;
			transition: border-color .2s, box-shadow .2s, background .2s;
			appearance: none;
		}

		.field-wrap input:focus,
		.field-wrap select:focus {
			border-color: var(--accent);
			background: #fff;
			box-shadow: 0 0 0 4px rgba(59, 91, 219, .1);
		}

		.field-wrap input::placeholder {
			color: #adb5bd;
		}

		/* Toggle senha */
		.toggle-pw {
			position: absolute;
			right: 14px;
			top: 50%;
			transform: translateY(-50%);
			background: none;
			border: none;
			cursor: pointer;
			color: var(--muted);
			font-size: 15px;
			padding: 0;
			line-height: 1;
		}

		.toggle-pw:hover {
			color: var(--accent);
		}

		/* Esqueceu senha */
		.forgot-link {
			display: block;
			text-align: right;
			font-size: 12.5px;
			color: var(--accent);
			text-decoration: none;
			margin-top: -6px;
			margin-bottom: 24px;
			font-weight: 500;
		}

		.forgot-link:hover {
			text-decoration: underline;
		}

		/* Botão principal */
		.btn-login {
			width: 100%;
			height: 50px;
			background: var(--accent);
			color: #fff;
			border: none;
			border-radius: 10px;
			font-family: var(--font);
			font-size: 15px;
			font-weight: 700;
			cursor: pointer;
			transition: background .2s, transform .15s, box-shadow .2s;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			letter-spacing: .01em;
		}

		.btn-login:hover {
			background: var(--accent-dark);
			box-shadow: 0 6px 20px rgba(59, 91, 219, .35);
			transform: translateY(-1px);
		}

		.btn-login:active {
			transform: translateY(0);
		}

		/* Rodapé */
		.form-footer {
			margin-top: 32px;
			text-align: center;
			font-size: 12px;
			color: var(--muted);
		}

		/* Responsivo */
		@media (max-width: 900px) {
			.login-panel {
				display: none;
			}

			.login-form-wrap {
				width: 100%;
				padding: 40px 24px;
			}
		}
	</style>
</head>

<body>

	<div class="login-page">

		<!-- ── Painel esquerdo ── -->
		<div class="login-panel">
			<img src="../app/img/logo.png" class="panel-logo" alt="Valiantus">
			<h1 class="panel-title">Gestão Veicular<br>Inteligente</h1>
			<p class="panel-sub">Plataforma completa para gestão de associados, contratos, boletos e relatórios financeiros.</p>
			<div class="panel-badges">
				<span class="panel-badge"><i class="fa-solid fa-shield-halved"></i> Seguro</span>
				<span class="panel-badge"><i class="fa-solid fa-bolt"></i> Rápido</span>
				<span class="panel-badge"><i class="fa-solid fa-chart-pie"></i> Relatórios</span>
				<span class="panel-badge"><i class="fa-solid fa-car"></i> Frota</span>
			</div>
		</div>

		<!-- ── Formulário ── -->
		<div class="login-form-wrap">
			<div class="form-inner">

				<div class="form-heading">
					<h2>Bem-vindo de volta</h2>
					<p>Faça login para acessar o painel</p>
				</div>

				<?php if (!empty($_SESSION['msg'])): ?>
					<div class="alert-erro">
						<i class="fa-solid fa-circle-exclamation"></i>
						<?= htmlspecialchars($_SESSION['msg']); ?>
						<?php unset($_SESSION['msg']); ?>
					</div>
				<?php endif; ?>

				<form method="post" action="">

					<!-- Tipo de usuário -->
					<div class="field-group">
						<label for="tipo_usuario">Tipo de Acesso</label>
						<div class="field-wrap">
							<i class="fa-solid fa-id-badge field-icon"></i>
							<select name="tipo_usuario" id="tipo_usuario" required>
								<option value="Administrador">Administrador</option>
								<option value="Atendente">Atendente</option>
								<option value="Vistoriador">Vistoriador</option>
								<option value="Caixa">Caixa</option>
							</select>
						</div>
					</div>

					<!-- E-mail -->
					<div class="field-group">
						<label for="username">E-mail</label>
						<div class="field-wrap">
							<i class="fa-solid fa-envelope field-icon"></i>
							<input type="text" id="username" name="username"
								placeholder="seu@email.com.br"
								autocomplete="username" required>
						</div>
					</div>

					<!-- Senha -->
					<div class="field-group">
						<label for="password">Senha</label>
						<div class="field-wrap">
							<i class="fa-solid fa-lock field-icon"></i>
							<input type="password" id="password" name="password"
								placeholder="••••••••"
								autocomplete="current-password" required>
							<button type="button" class="toggle-pw" id="togglePw" tabindex="-1">
								<i class="fa-regular fa-eye" id="togglePwIcon"></i>
							</button>
						</div>
					</div>

					<a href="javascript:;" class="forgot-link">Esqueceu a senha?</a>

					<button type="submit" name="enviar" class="btn-login">
						<i class="fa-solid fa-arrow-right-to-bracket"></i>
						Entrar no sistema
					</button>

				</form>

				<div class="form-footer">
					&copy; <?= date('Y') ?> Valiantus Associação Veicular. Todos os direitos reservados.
				</div>

			</div>
		</div>

	</div>

	<script>
		/* Toggle mostrar/ocultar senha */
		const pwInput = document.getElementById('password');
		const pwBtn = document.getElementById('togglePw');
		const pwIcon = document.getElementById('togglePwIcon');

		pwBtn.addEventListener('click', () => {
			const showing = pwInput.type === 'text';
			pwInput.type = showing ? 'password' : 'text';
			pwIcon.className = showing ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
		});
	</script>

</body>

</html>