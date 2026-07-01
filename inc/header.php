<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/valiantus/login.php');
    exit;
}

$sessNome  = htmlspecialchars($_SESSION['SessUsuNome']  ?? 'Usuário');
$sessEmail = htmlspecialchars($_SESSION['SessUsuEmail'] ?? '');
$sessTipo  = htmlspecialchars($_SESSION['SessUsuTipo']  ?? '');
$iniciais  = strtoupper(implode('', array_map(
    fn($p) => $p[0],
    array_slice(explode(' ', trim($sessNome ?: 'US')), 0, 2)
)));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($_SESSION['sessionTitulo'] ?? 'Valiantus') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Fontes -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Ícones -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs4/dt-1.13.8/r-2.5.0/datatables.min.css">

    <!-- Layout próprio Valiantus -->
    <link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/valiantus-layout.css">
    <link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/valiantus-tables.css">

    <!-- Metronic CSS (mantido para Bootstrap modals e DataTables theme) -->
    <link href="<?= APP_URL ?>/assets/vendors/base/vendors.bundle.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/demo/default/base/style.bundle.css" rel="stylesheet">

    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/app/media/img/logos/logo.ico">

    <!-- Tailwind CSS (preflight desabilitado para não conflitar com Bootstrap) -->
    <script>
        tailwind.config = {
            corePlugins: { preflight: false },
            theme: { extend: {} }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>

</head>
<body>

<!-- ════════════════════════════════════════
     TOPBAR
     ════════════════════════════════════════ -->
<header class="vl-topbar">

    <div class="vl-topbar-brand">
        <button class="vl-hamburger" id="vlHamburger" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <img src="<?= rtrim(APP_URL, '/') ?>/img/logo.png" alt="Valiantus">
        <div class="vl-topbar-brand-text">
            <strong>Valiantus</strong>
            <span>Associação Veicular</span>
        </div>
    </div>

    <div class="vl-topbar-actions">

        <!-- Notificações -->
        <div class="vl-tb-dropdown-wrap">
            <button class="vl-tb-btn" id="vlNotifBtn" title="Notificações">
                <i class="fa-regular fa-bell"></i>
                <span class="vl-tb-notif-dot"></span>
            </button>
            <div class="vl-tb-dropdown" id="vlNotifDrop">
                <div class="vl-drop-head">
                    <h6>Notificações</h6>
                    <span class="vl-drop-head-badge">2 novas</span>
                </div>
                <div class="vl-notif-list">
                    <div class="vl-notif-item">
                        <span class="vl-notif-dot warn"></span>
                        <div>
                            <div class="vl-notif-text">173 cobranças vencendo hoje</div>
                            <div class="vl-notif-time">Agora</div>
                        </div>
                    </div>
                    <div class="vl-notif-item">
                        <span class="vl-notif-dot"></span>
                        <div>
                            <div class="vl-notif-text">15 aniversariantes hoje</div>
                            <div class="vl-notif-time">há 5 min</div>
                        </div>
                    </div>
                </div>
                <div class="vl-drop-footer"><a href="#">Ver todas</a></div>
            </div>
        </div>

        <!-- Perfil -->
        <div class="vl-tb-dropdown-wrap">
            <div class="vl-avatar" id="vlProfileBtn" title="<?= $sessNome ?>"><?= $iniciais ?></div>
            <div class="vl-tb-dropdown vl-profile-drop" id="vlProfileDrop">
                <div class="vl-profile-info">
                    <div class="vl-profile-avatar-lg"><?= $iniciais ?></div>
                    <div class="vl-profile-name"><?= $sessNome ?></div>
                    <?php if ($sessTipo): ?>
                        <span class="vl-profile-role"><?= $sessTipo ?></span>
                    <?php endif; ?>
                </div>
                <div class="vl-profile-menu">
                    <a href="<?= APP_URL ?>/meus-dados/" class="vl-profile-item">
                        <i class="fa-regular fa-user"></i> Meu Perfil
                    </a>
                    <div class="vl-profile-divider"></div>
                    <a href="#"
                       data-logout-url="<?= rtrim(APP_URL, '/') ?>/logout/logout.php"
                       class="vl-profile-item logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Sair do sistema
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>

<!-- Overlay mobile -->
<div class="vl-overlay" id="vlOverlay"></div>

<!-- ════════════════════════════════════════
     SIDEBAR
     ════════════════════════════════════════ -->
<aside class="vl-sidebar" id="vlSidebar">
    <nav class="vl-sidebar-nav">
        <ul style="list-style:none;padding:0;margin:0;">

            <li class="vl-sb-item">
                <a href="<?= APP_URL ?>/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="vl-sb-label">Início</span>
                </a>
            </li>

            <div class="vl-sb-section">Cadastros</div>

            <li class="vl-sb-item">
                <button class="vl-sb-link" onclick="vlSbToggle(this)" aria-expanded="false">
                    <span class="vl-sb-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="vl-sb-label">Cadastros</span>
                    <i class="fa-solid fa-chevron-right vl-sb-arrow"></i>
                </button>
                <ul class="vl-sb-submenu">
                    <li><a href="<?= APP_URL ?>/meus-dados/"  class="vl-sb-sub-link">Meus Dados</a></li>
                    <li><a href="<?= APP_URL ?>/associados/" class="vl-sb-sub-link">Associados</a></li>
                    <li><a href="<?= APP_URL ?>/grupo/"      class="vl-sb-sub-link">Tabela de Preço</a></li>
                    <li><a href="<?= APP_URL ?>/rastreador/" class="vl-sb-sub-link">Rastreador</a></li>
                    <li><a href="<?= APP_URL ?>/combo/"      class="vl-sb-sub-link">Combo</a></li>
                    <li><a href="<?= APP_URL ?>/vendedor/"      class="vl-sb-sub-link">Vendedores</a></li>
                    <li><a href="<?= APP_URL ?>/vistoriador/" class="vl-sb-sub-link">Vistoriadores</a></li>
                    <li><a href="<?= APP_URL ?>/cores/"       class="vl-sb-sub-link">Cores</a></li>
                </ul>
            </li>

            <div class="vl-sb-section">Financeiro</div>

            <li class="vl-sb-item">
                <button class="vl-sb-link" onclick="vlSbToggle(this)" aria-expanded="false">
                    <span class="vl-sb-icon"><i class="fa-solid fa-arrows-rotate"></i></span>
                    <span class="vl-sb-label">Movimentação</span>
                    <i class="fa-solid fa-chevron-right vl-sb-arrow"></i>
                </button>
                <ul class="vl-sb-submenu">
                    <li><a href="#" class="vl-sb-sub-link">Gerar Boletos</a></li>
                    <li><a href="<?= APP_URL ?>/movimentacao/gerar-remessa/" class="vl-sb-sub-link">Gerar Remessa</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Gerar Remessa Avulsa</a></li>
                    <li><a href="<?= APP_URL ?>/movimentacao/processar-retorno/" class="vl-sb-sub-link">Processar Retorno</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Espelho de Retorno</a></li>
                </ul>
            </li>

            <div class="vl-sb-section">Operações</div>

            <li class="vl-sb-item">
                <a href="<?= APP_URL ?>/sinistros/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-car-burst"></i></span>
                    <span class="vl-sb-label">Sinistros</span>
                </a>
            </li>

            <li class="vl-sb-item">
                <a href="<?= APP_URL ?>/baixar-parcela/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                    <span class="vl-sb-label">Baixa Manual</span>
                </a>
            </li>

            <li class="vl-sb-item">
                <button class="vl-sb-link" onclick="vlSbToggle(this)" aria-expanded="false">
                    <span class="vl-sb-icon"><i class="fa-solid fa-print"></i></span>
                    <span class="vl-sb-label">Impressões</span>
                    <i class="fa-solid fa-chevron-right vl-sb-arrow"></i>
                </button>
                <ul class="vl-sb-submenu">
                    <li><a href="<?= APP_URL ?>/impressoes/checklist_branco.php" target="_blank" class="vl-sb-sub-link">Checklist (em branco)</a></li>
                    <li><a href="<?= APP_URL ?>/impressoes/tabela_preco.php" target="_blank" class="vl-sb-sub-link">Tabela de Preços</a></li>
                </ul>
            </li>

            <li class="vl-sb-item">
                <button class="vl-sb-link" onclick="vlSbToggle(this)" aria-expanded="false">
                    <span class="vl-sb-icon"><i class="fa-solid fa-chart-bar"></i></span>
                    <span class="vl-sb-label">Relatórios</span>
                    <i class="fa-solid fa-chevron-right vl-sb-arrow"></i>
                </button>
                <ul class="vl-sb-submenu">
                    <li><a href="#" class="vl-sb-sub-link">Fechamento</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Cancelamento</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Renovações</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Adesão / Renovação</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Recebimentos Detalhado</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Recebimentos Agrupado</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Parcelas em Atraso</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Sinistros</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Pagantes por Cidade</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Clientes com Combo</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Boletos Pagos</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Contabilidade</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Recebimentos por Usuário</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Demonstrativo Financeiro</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Tipo de Boleto</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Bancários pagos na Empresa</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Boletos Geral</a></li>
                    <li><a href="#" class="vl-sb-sub-link">Recebimentos por Tipo</a></li>
                </ul>
            </li>

            <div class="vl-sb-section">Sistema</div>

            <li class="vl-sb-item">
                <a href="<?= APP_URL ?>/usuarios/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-user-gear"></i></span>
                    <span class="vl-sb-label">Usuários</span>
                </a>
            </li>

            <li class="vl-sb-item">
                <a href="<?= APP_URL ?>/configuracoes/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="vl-sb-label">Configurações</span>
                </a>
            </li>

            <li class="vl-sb-item">
                <a href="<?= BASE_URL ?>/migrar/" class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-database"></i></span>
                    <span class="vl-sb-label">Migração Valiantus</span>
                </a>
            </li>

            <div class="vl-sb-divider"></div>

            <li class="vl-sb-item vl-sb-logout">
                <a href="#"
                   data-logout-url="<?= rtrim(APP_URL, '/') ?>/logout/logout.php"
                   class="vl-sb-link">
                    <span class="vl-sb-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="vl-sb-label">Sair do sistema</span>
                </a>
            </li>

        </ul>
    </nav>
</aside>

<!-- ════════════════════════════════════════
     CONTEÚDO — abre aqui, fecha no footer.php
     ════════════════════════════════════════ -->
<main class="vl-content">
