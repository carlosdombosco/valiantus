<?php
require_once PATH_INC . '/db.php';
require_once __DIR__ . '/metrics.php';

$totalContratosAtivos = total_contratos_ativos($pdo);
$cancelamentosMes     = total_cancelamentos_mes_atual($pdo);
$stats                = dash_stats($pdo);
?>

<!-- ── Estilos do Dashboard ─────────────────────────────────────── -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    :root {
        --dash-bg: #f0f2f8;
        --card-bg: #ffffff;
        --card-radius: 16px;
        --card-shadow: 0 2px 16px rgba(30, 40, 80, .07);
        --accent: #3b5bdb;
        --accent-light: #e8edff;
        --green: #2f9e44;
        --green-light: #d3f9d8;
        --amber: #e67700;
        --amber-light: #fff3bf;
        --red: #c92a2a;
        --red-light: #ffe3e3;
        --teal: #0b7285;
        --teal-light: #e3fafc;
        --violet: #6741d9;
        --violet-light: #ede9fe;
        --slate: #495057;
        --ink: #1a1d2e;
        --muted: #868e96;
        --border: #e9ecef;
        --font: 'Plus Jakarta Sans', 'Poppins', sans-serif;
    }

    .dash-wrap {
        font-family: var(--font);
        background: var(--dash-bg);
        padding: 28px 28px 40px;
        min-height: 100vh;
    }

    /* ── Cabeçalho ── */
    .dash-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .dash-header-left h1 {
        font-size: 22px;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 2px;
        letter-spacing: -.3px;
    }

    .dash-header-left p {
        font-size: 13px;
        color: var(--muted);
        margin: 0;
    }

    .dash-date-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        color: var(--slate);
        box-shadow: var(--card-shadow);
    }

    .dash-date-badge i {
        color: var(--accent);
    }

    /* ── Grid de KPIs ── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .kpi-grid-2 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .kpi-card {
        background: var(--card-bg);
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
        padding: 20px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        border: 1px solid var(--border);
        transition: transform .18s ease, box-shadow .18s ease;
        position: relative;
        overflow: hidden;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        border-radius: 4px 0 0 4px;
        background: var(--kpi-accent, var(--accent));
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(30, 40, 80, .12);
    }

    .kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: var(--kpi-bg, var(--accent-light));
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .kpi-icon i {
        font-size: 20px;
        color: var(--kpi-accent, var(--accent));
    }

    .kpi-body {
        flex: 1;
        min-width: 0;
    }

    .kpi-label {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 4px;
    }

    .kpi-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1;
        letter-spacing: -.5px;
    }

    .kpi-sub {
        font-size: 11px;
        color: var(--muted);
        margin-top: 4px;
    }

    /* variações de cor */
    .kpi--blue {
        --kpi-accent: var(--accent);
        --kpi-bg: var(--accent-light);
    }

    .kpi--green {
        --kpi-accent: var(--green);
        --kpi-bg: var(--green-light);
    }

    .kpi--amber {
        --kpi-accent: var(--amber);
        --kpi-bg: var(--amber-light);
    }

    .kpi--red {
        --kpi-accent: var(--red);
        --kpi-bg: var(--red-light);
    }

    .kpi--teal {
        --kpi-accent: var(--teal);
        --kpi-bg: var(--teal-light);
    }

    .kpi--violet {
        --kpi-accent: var(--violet);
        --kpi-bg: var(--violet-light);
    }

    .kpi--slate {
        --kpi-accent: var(--slate);
        --kpi-bg: #f1f3f5;
    }

    .kpi--dark {
        --kpi-accent: #1a1d2e;
        --kpi-bg: #e9ecef;
    }

    /* ── Card aniversariantes ── */
    .birthday-card {
        background: linear-gradient(135deg, var(--accent) 0%, #4c6ef5 100%);
        border-radius: var(--card-radius);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        box-shadow: 0 6px 24px rgba(59, 91, 219, .25);
        border: none;
    }

    .birthday-card-text h4 {
        font-size: 15px;
        font-weight: 600;
        color: rgba(255, 255, 255, .85);
        margin: 0 0 4px;
    }

    .birthday-card-text span {
        font-size: 32px;
        font-weight: 700;
        color: #fff;
        letter-spacing: -1px;
    }

    .birthday-card-text small {
        font-size: 12px;
        color: rgba(255, 255, 255, .65);
        display: block;
        margin-top: 2px;
    }

    .birthday-card-icon {
        font-size: 48px;
        opacity: .3;
    }

    /* ── Grid de gráficos ── */
    .charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .chart-card {
        background: var(--card-bg);
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .chart-card-header {
        padding: 18px 22px 14px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .chart-card-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--ink);
        margin: 0;
    }

    .chart-card-subtitle {
        font-size: 12px;
        color: var(--muted);
        margin: 2px 0 0;
    }

    .chart-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 99px;
        background: var(--accent-light);
        color: var(--accent);
    }

    .chart-card-body {
        padding: 16px 22px 20px;
    }

    /* ── Responsive ── */
    @media (max-width: 1100px) {

        .kpi-grid,
        .kpi-grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .dash-wrap {
            padding: 16px;
        }

        .kpi-grid,
        .kpi-grid-2 {
            grid-template-columns: 1fr 1fr;
        }

        .charts-grid {
            grid-template-columns: 1fr;
        }

        .kpi-value {
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {

        .kpi-grid,
        .kpi-grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ── Marcação ──────────────────────────────────────────────────── -->
<div class="dash-wrap">

    <!-- Cabeçalho -->
    <div class="dash-header">
        <div class="dash-header-left">
            <h1>Painel de Controle</h1>
            <p>Visão geral da associação em tempo real</p>
        </div>
        <div class="dash-date-badge">
            <i class="fa-regular fa-calendar"></i>
            <span id="dashDataAtual"></span>
        </div>
    </div>

    <!-- Linha 1: KPIs principais -->
    <div class="kpi-grid">
        <div class="kpi-card kpi--blue">
            <div class="kpi-icon"><i class="fa-solid fa-file-contract"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Contratos Ativos</div>
                <div class="kpi-value"><?= number_format($totalContratosAtivos, 0, ',', '.') ?></div>
                <div class="kpi-sub">Total na base</div>
            </div>
        </div>

        <div class="kpi-card kpi--green">
            <div class="kpi-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Recebido Hoje</div>
                <div class="kpi-value">R$ 0,00</div>
                <div class="kpi-sub">Pagamentos confirmados</div>
            </div>
        </div>

        <div class="kpi-card kpi--teal">
            <div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Projeção Hoje</div>
                <div class="kpi-value">R$ 0,00</div>
                <div class="kpi-sub">Esperado para o dia</div>
            </div>
        </div>

        <div class="kpi-card kpi--red">
            <div class="kpi-icon"><i class="fa-solid fa-ban"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Cancelamentos</div>
                <div class="kpi-value"><?= number_format($cancelamentosMes, 0, ',', '.') ?></div>
                <div class="kpi-sub">No mês atual</div>
            </div>
        </div>
    </div>

    <!-- Linha 2: KPIs secundários -->
    <div class="kpi-grid-2">
        <div class="kpi-card kpi--amber">
            <div class="kpi-icon"><i class="fa-solid fa-car"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Veículos Ativos</div>
                <div class="kpi-value"><?= number_format($stats['veic_ativos'], 0, ',', '.') ?></div>
                <div class="kpi-sub">Com contrato ativo</div>
            </div>
        </div>

        <div class="kpi-card kpi--slate">
            <div class="kpi-icon"><i class="fa-solid fa-car-on"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Veículos Inativos</div>
                <div class="kpi-value"><?= number_format($stats['veic_inativos'], 0, ',', '.') ?></div>
                <div class="kpi-sub">Sem contrato ativo</div>
            </div>
        </div>

        <div class="kpi-card kpi--dark">
            <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Associados Ativos</div>
                <div class="kpi-value"><?= number_format($stats['assoc_ativos'], 0, ',', '.') ?></div>
                <div class="kpi-sub">Com contrato ativo</div>
            </div>
        </div>

        <div class="kpi-card kpi--violet">
            <div class="kpi-icon"><i class="fa-solid fa-user-xmark"></i></div>
            <div class="kpi-body">
                <div class="kpi-label">Associados Inativos</div>
                <div class="kpi-value"><?= number_format($stats['assoc_inativos'], 0, ',', '.') ?></div>
                <div class="kpi-sub">Sem contrato ativo</div>
            </div>
        </div>
    </div>

    <!-- Banner aniversariantes -->
    <div class="birthday-card">
        <div class="birthday-card-text">
            <h4><i class="fa-solid fa-cake-candles" style="margin-right:6px;"></i>Aniversariantes Hoje</h4>
            <span><?= $stats['aniversariantes_hoje'] ?></span>
            <small><?= $stats['aniversariantes_hoje'] === 1 ? 'associado faz aniversário hoje' : 'associados fazem aniversário hoje' ?></small>
        </div>
        <i class="fa-solid fa-gift birthday-card-icon" style="color:#fff;"></i>
    </div>

    <!-- Gráficos -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <p class="chart-card-title">Recebimentos — Últimos 7 dias</p>
                    <p class="chart-card-subtitle">Valores consolidados por dia</p>
                </div>
                <span class="chart-badge">Semana</span>
            </div>
            <div class="chart-card-body">
                <div id="graficoBarras"></div>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <p class="chart-card-title">Distribuição de Veículos</p>
                    <p class="chart-card-subtitle">Ativos vs Inativos</p>
                </div>
                <span class="chart-badge">Base atual</span>
            </div>
            <div class="chart-card-body">
                <div id="graficoPizza"></div>
            </div>
        </div>
    </div>

</div><!-- /.dash-wrap -->

<!-- ── Scripts ───────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
<script>
    (function() {

        /* Data atual no badge */
        const now = new Date();
        document.getElementById('dashDataAtual').textContent =
            now.toLocaleDateString('pt-BR', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });

        /* ── Paleta comum ── */
        const fontFamily = "'Plus Jakarta Sans', 'Poppins', sans-serif";

        /* ── Gráfico de barras ── */
        new ApexCharts(document.getElementById('graficoBarras'), {
            chart: {
                type: 'bar',
                height: 240,
                toolbar: {
                    show: false
                },
                fontFamily: fontFamily,
                animations: {
                    enabled: true,
                    speed: 600
                }
            },
            series: [{
                name: 'Recebido',
                data: [7500, 8000, 5600, 9200, 11750, 14500, 6000]
            }],
            xaxis: {
                categories: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        fontSize: '12px',
                        colors: '#868e96'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: v => 'R$ ' + (v / 1000).toFixed(1) + 'k',
                    style: {
                        fontSize: '11px',
                        colors: '#868e96'
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.3,
                    gradientToColors: ['#4c6ef5'],
                    stops: [0, 100]
                }
            },
            colors: ['#3b5bdb'],
            plotOptions: {
                bar: {
                    borderRadius: 8,
                    columnWidth: '55%'
                }
            },
            dataLabels: {
                enabled: false
            },
            grid: {
                borderColor: '#f1f3f5',
                strokeDashArray: 4,
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: v => 'R$ ' + v.toLocaleString('pt-BR')
                }
            }
        }).render();

        /* ── Gráfico de rosca ── */
        new ApexCharts(document.getElementById('graficoPizza'), {
            chart: {
                type: 'donut',
                height: 240,
                fontFamily: fontFamily,
                animations: {
                    enabled: true,
                    speed: 600
                }
            },
            series: [<?= $stats['veic_ativos'] ?>, <?= $stats['veic_inativos'] ?>],
            labels: ['Ativos', 'Inativos'],
            colors: ['#2f9e44', '#c92a2a'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '13px',
                                fontWeight: 600,
                                color: '#868e96',
                                formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            },
                            value: {
                                fontSize: '22px',
                                fontWeight: 700,
                                color: '#1a1d2e'
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                position: 'bottom',
                fontSize: '13px',
                fontWeight: 500,
                markers: {
                    radius: 6
                }
            },
            stroke: {
                width: 0
            },
            tooltip: {
                y: {
                    formatter: v => v + ' veículos'
                }
            }
        }).render();

    })();
</script>