<?php
// /valiantus/inc/config.php
date_default_timezone_set('America/Recife');

// Marque que este config já foi carregado (opcional, mas útil)
if (!defined('VALIANTUS_CONFIG')) define('VALIANTUS_CONFIG', true);

/** Caminhos físicos */
if (!defined('PATH_ROOT'))   define('PATH_ROOT', realpath(__DIR__ . '/..'));
if (!defined('PATH_APP'))    define('PATH_APP', PATH_ROOT . '/app');
if (!defined('PATH_INC'))    define('PATH_INC', PATH_ROOT . '/inc');
if (!defined('PATH_ACTION')) define('PATH_ACTION', PATH_ROOT . '/actions');
if (!defined('PATH_LOGIN'))  define('PATH_LOGIN', PATH_ROOT . '/login');
if (!defined('PATH_UPLOAD')) define('PATH_UPLOAD', PATH_ROOT . '/uploads');

/** URLs base */
if (!defined('BASE_URL'))    define('BASE_URL', '/valiantus');

/** Sicoob API — preencher antes de ativar a integração */
if (!defined('SICOOB_CLIENT_ID'))     define('SICOOB_CLIENT_ID',     '');
if (!defined('SICOOB_CLIENT_SECRET')) define('SICOOB_CLIENT_SECRET', '');
if (!defined('SICOOB_COOPERATIVA'))   define('SICOOB_COOPERATIVA',   0);   // ex: 3234
if (!defined('SICOOB_CONTA'))         define('SICOOB_CONTA',         0);   // número da conta corrente
if (!defined('SICOOB_MODALIDADE'))    define('SICOOB_MODALIDADE',    1);   // 1=simples sem registro
if (!defined('SICOOB_CERT_PATH'))     define('SICOOB_CERT_PATH',     ''); // caminho absoluto ao .pem
if (!defined('SICOOB_CERT_KEY'))      define('SICOOB_CERT_KEY',      ''); // chave privada do cert
if (!defined('SICOOB_DEV'))           define('SICOOB_DEV',           true); // false em produção
if (!defined('APP_URL'))     define('APP_URL', BASE_URL . '/app');
if (!defined('LOGIN_URL'))   define('LOGIN_URL', BASE_URL . '/login');
if (!defined('ACTION_URL'))  define('ACTION_URL', BASE_URL . '/actions');
if (!defined('UPLOAD_URL'))  define('UPLOAD_URL', BASE_URL . '/uploads');
