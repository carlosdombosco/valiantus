<style>
	/* ══════════════════════════════════════════
   Sidebar — Valiantus (sobrescreve Metronic)
   ══════════════════════════════════════════ */
	/* @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');*/

	@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

	:root {
		--sb-bg: #1a1d2e;
		--sb-bg2: #20243a;
		--sb-accent: #3b5bdb;
		--sb-accent-bg: rgba(59, 91, 219, .15);
		--sb-text: rgba(255, 255, 255, .65);
		--sb-text-on: #ffffff;
		--sb-border: rgba(255, 255, 255, .07);
		--sb-hover: rgba(255, 255, 255, .06);
		--sb-radius: 10px;
		--sb-font: 'Plus Jakarta Sans', 'Poppins', sans-serif;
	}

	/* Container raiz do aside */
	#m_aside_left {
		background: var(--sb-bg) !important;
		border-right: 1px solid var(--sb-border) !important;
	}

	/* ── Logo area ── */
	.sb-logo-area {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 22px 20px 18px;
		border-bottom: 1px solid var(--sb-border);
		margin-bottom: 8px;
	}

	.sb-logo-area img {
		width: 36px;
		height: 36px;
		object-fit: contain;
		border-radius: 8px;
	}

	.sb-logo-text {
		display: flex;
		flex-direction: column;
	}

	.sb-logo-text strong {
		font-family: var(--sb-font);
		font-size: 14px;
		font-weight: 700;
		color: var(--sb-text-on);
		line-height: 1.2;
	}

	.sb-logo-text span {
		font-family: var(--sb-font);
		font-size: 10.5px;
		font-weight: 500;
		color: var(--sb-text);
		letter-spacing: .03em;
	}

	/* ── Nav wrapper ── */
	.sb-nav {
		padding: 0 12px 20px;
		font-family: var(--sb-font);
	}

	/* Separador de seção */
	.sb-section-label {
		font-size: 10px;
		font-weight: 700;
		letter-spacing: .1em;
		text-transform: uppercase;
		color: rgba(255, 255, 255, .3);
		padding: 16px 10px 6px;
	}

	/* Item simples */
	.sb-item {
		list-style: none;
	}

	.sb-link {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 12px;
		border-radius: var(--sb-radius);
		text-decoration: none;
		color: var(--sb-text);
		font-size: 13.5px;
		font-weight: 500;
		transition: background .15s, color .15s;
		cursor: pointer;
		border: none;
		background: none;
		width: 100%;
		text-align: left;
	}

	.sb-link:hover {
		background: var(--sb-hover);
		color: var(--sb-text-on);
		text-decoration: none;
	}

	.sb-link.active,
	.sb-link[aria-expanded="true"] {
		background: var(--sb-accent-bg);
		color: var(--sb-text-on);
	}

	.sb-link-icon {
		width: 32px;
		height: 32px;
		border-radius: 8px;
		background: rgba(255, 255, 255, .06);
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		font-size: 13px;
		transition: background .15s;
	}

	.sb-link:hover .sb-link-icon,
	.sb-link.active .sb-link-icon,
	.sb-link[aria-expanded="true"] .sb-link-icon {
		background: var(--sb-accent);
		color: #fff;
	}

	.sb-link-text {
		flex: 1;
	}

	.sb-arrow {
		font-size: 10px;
		opacity: .5;
		transition: transform .25s;
	}

	.sb-link[aria-expanded="true"] .sb-arrow {
		transform: rotate(90deg);
		opacity: 1;
	}

	/* Submenu */
	.sb-submenu {
		list-style: none;
		padding: 4px 0 4px 44px;
		margin: 0;
		display: none;
	}

	.sb-submenu.open {
		display: block;
	}

	.sb-sub-link {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 10px;
		border-radius: 8px;
		text-decoration: none;
		color: var(--sb-text);
		font-size: 13px;
		font-weight: 400;
		transition: background .15s, color .15s;
	}

	.sb-sub-link::before {
		content: '';
		width: 5px;
		height: 5px;
		border-radius: 50%;
		background: rgba(255, 255, 255, .25);
		flex-shrink: 0;
		transition: background .15s;
	}

	.sb-sub-link:hover {
		background: var(--sb-hover);
		color: var(--sb-text-on);
		text-decoration: none;
	}

	.sb-sub-link:hover::before {
		background: var(--sb-accent);
	}

	.sb-sub-link.active {
		color: var(--sb-text-on);
	}

	.sb-sub-link.active::before {
		background: var(--sb-accent);
	}

	/* Divider */
	.sb-divider {
		height: 1px;
		background: var(--sb-border);
		margin: 10px 10px;
	}

	/* Item de logout */
	.sb-logout .sb-link:hover {
		background: rgba(201, 42, 42, .15);
		color: #ff8787;
	}

	.sb-logout .sb-link:hover .sb-link-icon {
		background: rgba(201, 42, 42, .7);
		color: #fff;
	}
</style>

<div class="sb-logo-area">
	<img src="<?= rtrim(APP_URL, '/') ?>/img/logo.png" alt="Valiantus">
	<div class="sb-logo-text">
		<strong>Valiantus</strong>
		<span>Associação Veicular</span>
	</div>
</div>

<nav class="sb-nav">
	<ul style="list-style:none;padding:0;margin:0;">

		<!-- Início -->
		<li class="sb-item">
			<a href="<?= APP_URL ?>/" class="sb-link">
				<span class="sb-link-icon"><i class="fa-solid fa-house"></i></span>
				<span class="sb-link-text">Início</span>
			</a>
		</li>

		<div class="sb-section-label">Cadastros</div>

		<!-- Cadastros -->
		<li class="sb-item">
			<button class="sb-link" onclick="sbToggle(this)" aria-expanded="false">
				<span class="sb-link-icon"><i class="fa-solid fa-users"></i></span>
				<span class="sb-link-text">Cadastros</span>
				<i class="fa-solid fa-chevron-right sb-arrow"></i>
			</button>
			<ul class="sb-submenu">
				<li><a href="#" class="sb-sub-link">Meus Dados</a></li>
				<li><a href="<?= APP_URL ?>/associados/" class="sb-sub-link">Associados</a></li>
				<li><a href="<?= APP_URL ?>/grupo/" class="sb-sub-link">Tabela de Preço</a></li>
				<li><a href="<?= APP_URL ?>/rastreador/" class="sb-sub-link">Rastreador</a></li>
				<li><a href="<?= APP_URL ?>/combo/" class="sb-sub-link">Combo</a></li>
				<li><a href="<?= APP_URL ?>/vendedor/" class="sb-sub-link">Vendedores</a></li>
				<li><a href="<?= APP_URL ?>/cores/" class="sb-sub-link">Cores</a></li>
			</ul>
		</li>

		<div class="sb-section-label">Financeiro</div>

		<!-- Movimentação -->
		<li class="sb-item">
			<button class="sb-link" onclick="sbToggle(this)" aria-expanded="false">
				<span class="sb-link-icon"><i class="fa-solid fa-arrows-rotate"></i></span>
				<span class="sb-link-text">Movimentação</span>
				<i class="fa-solid fa-chevron-right sb-arrow"></i>
			</button>
			<ul class="sb-submenu">
				<li><a href="#" class="sb-sub-link">Gerar Boletos</a></li>
				<li><a href="#" class="sb-sub-link">Gerar Remessa</a></li>
				<li><a href="#" class="sb-sub-link">Gerar Remessa Avulsa</a></li>
				<li><a href="#" class="sb-sub-link">Processar Retorno</a></li>
				<li><a href="#" class="sb-sub-link">Espelho de Retorno</a></li>
			</ul>
		</li>

		<div class="sb-section-label">Operações</div>

		<!-- Sinistros -->
		<li class="sb-item">
			<a href="<?= APP_URL ?>/sinistros/" class="sb-link">
				<span class="sb-link-icon"><i class="fa-solid fa-car-burst"></i></span>
				<span class="sb-link-text">Sinistros</span>
			</a>
		</li>

		<!-- Impressões -->
		<li class="sb-item">
			<button class="sb-link" onclick="sbToggle(this)" aria-expanded="false">
				<span class="sb-link-icon"><i class="fa-solid fa-print"></i></span>
				<span class="sb-link-text">Impressões</span>
				<i class="fa-solid fa-chevron-right sb-arrow"></i>
			</button>
			<ul class="sb-submenu">
				<li><a href="<?= APP_URL ?>/impressoes/checklist_branco.php" target="_blank" class="sb-sub-link">Checklist (em branco)</a></li>
				<li><a href="<?= APP_URL ?>/impressoes/estatuto.php" target="_blank" class="sb-sub-link">Estatuto</a></li>
				<li><a href="<?= APP_URL ?>/impressoes/regimento.php" target="_blank" class="sb-sub-link">Regimento</a></li>
				<li><a href="#" class="sb-sub-link">Tabela de Preço</a></li>
				<li><a href="#" class="sb-sub-link">Recibo de Filiação</a></li>
				<li><a href="#" class="sb-sub-link">Serviços Adicionais</a></li>
			</ul>
		</li>

		<!-- Relatórios -->
		<li class="sb-item">
			<button class="sb-link" onclick="sbToggle(this)" aria-expanded="false">
				<span class="sb-link-icon"><i class="fa-solid fa-chart-bar"></i></span>
				<span class="sb-link-text">Relatórios</span>
				<i class="fa-solid fa-chevron-right sb-arrow"></i>
			</button>
			<ul class="sb-submenu">
				<li><a href="#" class="sb-sub-link">Fechamento</a></li>
				<li><a href="#" class="sb-sub-link">Cancelamento</a></li>
				<li><a href="#" class="sb-sub-link">Renovações</a></li>
				<li><a href="#" class="sb-sub-link">Adesão / Renovação</a></li>
				<li><a href="#" class="sb-sub-link">Recebimentos Detalhado</a></li>
				<li><a href="#" class="sb-sub-link">Recebimentos Agrupado</a></li>
				<li><a href="#" class="sb-sub-link">Parcelas em Atraso</a></li>
				<li><a href="#" class="sb-sub-link">Sinistros</a></li>
				<li><a href="#" class="sb-sub-link">Pagantes por Cidade</a></li>
				<li><a href="#" class="sb-sub-link">Clientes com Combo</a></li>
				<li><a href="#" class="sb-sub-link">Boletos Pagos</a></li>
				<li><a href="#" class="sb-sub-link">Contabilidade</a></li>
				<li><a href="#" class="sb-sub-link">Recebimentos por Usuário</a></li>
				<li><a href="#" class="sb-sub-link">Demonstrativo Financeiro</a></li>
				<li><a href="#" class="sb-sub-link">Tipo de Boleto</a></li>
				<li><a href="#" class="sb-sub-link">Bancários pagos na Empresa</a></li>
				<li><a href="#" class="sb-sub-link">Boletos Geral</a></li>
				<li><a href="#" class="sb-sub-link">Recebimentos por Tipo</a></li>
			</ul>
		</li>

		<div class="sb-divider"></div>

		<!-- Sair -->
		<li class="sb-item sb-logout">
			<a href="#"
				data-logout-url="<?= rtrim(APP_URL, '/') ?>/logout/logout.php"
				class="sb-link">
				<span class="sb-link-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
				<span class="sb-link-text">Sair do sistema</span>
			</a>
		</li>

	</ul>
</nav>

<script>
	function sbToggle(btn) {
		const submenu = btn.nextElementSibling;
		const expanded = btn.getAttribute('aria-expanded') === 'true';

		// Fecha outros abertos
		document.querySelectorAll('.sb-nav .sb-link[aria-expanded="true"]').forEach(b => {
			if (b !== btn) {
				b.setAttribute('aria-expanded', 'false');
				b.nextElementSibling?.classList.remove('open');
			}
		});

		btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		submenu?.classList.toggle('open', !expanded);
	}

	// Marca link ativo pelo pathname
	(function() {
		const path = window.location.pathname;
		document.querySelectorAll('.sb-sub-link, .sb-link').forEach(a => {
			if (a.tagName === 'A' && a.getAttribute('href') !== '#' && path.includes(a.getAttribute('href'))) {
				a.classList.add('active');
				// abre o submenu pai se houver
				const sub = a.closest('.sb-submenu');
				if (sub) {
					sub.classList.add('open');
					const btn = sub.previousElementSibling;
					if (btn) btn.setAttribute('aria-expanded', 'true');
				}
			}
		});
	})();
</script>