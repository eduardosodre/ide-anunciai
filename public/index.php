<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require $projectRoot . '/src/bootstrap.php';

use App\Config\AppConfig;
use App\Controllers\AccountController;
use App\Controllers\ApiController;
use App\Controllers\AuthWebController;
use App\Controllers\ChatController;
use App\Controllers\ChurchController;
use App\Controllers\HomeController;
use App\Controllers\ModerationController;
use App\Controllers\ProfessionalController;
use App\Controllers\VerificationController;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ChatRepository;
use App\Repository\ChurchRepository;
use App\Repository\ProfessionalRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\PasswordValidator;
use App\Service\AuthService;
use App\Service\ChatService;
use App\Service\GeoLocationService;
use App\Service\Mailer;
use App\Service\ReportService;
use App\Service\SearchService;
use App\Service\ViaCepClient;

try {
$config = AppConfig::load($projectRoot);
$pdo = Connection::get($config->database());
$userRepo = new UserRepository($pdo);
$professionalRepo = new ProfessionalRepository($pdo);
$churchRepo = new ChurchRepository($pdo);
$verificationRepo = new VerificationRepository($pdo);
$reportRepo = new ReportRepository($pdo);
$reportService = new ReportService($userRepo, $reportRepo);
$chatRepo = new ChatRepository($pdo);
$viaCep = new ViaCepClient();
$geo = new GeoLocationService($viaCep);
$searchService = new SearchService($professionalRepo, $churchRepo, $geo);
$mailer = new Mailer($projectRoot);
$chatService = new ChatService($chatRepo, $userRepo, $professionalRepo, $churchRepo, $mailer, $config);
$passwordValidator = new PasswordValidator();
$authService = new AuthService($userRepo, $passwordValidator, $mailer, $config);
$csrf = new Csrf();

$homeController = new HomeController($csrf, $searchService, $professionalRepo);
$authWebController = new AuthWebController($authService, $csrf);
$accountController = new AccountController($authService, $userRepo, $csrf);
$professionalController = new ProfessionalController($professionalRepo, $verificationRepo, $geo, $csrf);
$churchController = new ChurchController($churchRepo, $userRepo, $verificationRepo, $geo, $viaCep, $csrf);
$verificationController = new VerificationController($verificationRepo, $professionalRepo, $churchRepo, $userRepo, $csrf);
$moderationController = new ModerationController($reportService, $userRepo, $csrf);
$chatController = new ChatController($chatRepo, $chatService, $csrf);
$apiController = new ApiController($authService, $userRepo, $reportService, $searchService);

$request = Request::fromGlobals();
$router = new Router();

$router->get('/', [$homeController, 'home']);
$router->get('/busca', [$homeController, 'busca']);
$router->get('/privacidade', [$homeController, 'privacidade']);
$router->get('/chat/iniciar', [$chatController, 'iniciarGet']);
$router->post('/chat/iniciar', [$chatController, 'iniciarPost']);
$router->get('/chat', [$chatController, 'listGet']);
$router->get('/chat/{id}', [$chatController, 'showGet']);
$router->post('/chat/{id}/mensagens', [$chatController, 'messagePost']);

$router->get('/cadastro', [$authWebController, 'cadastroGet']);
$router->post('/cadastro', [$authWebController, 'cadastroPost']);
$router->get('/login', [$authWebController, 'loginGet']);
$router->post('/login', [$authWebController, 'loginPost']);
$router->post('/sair', [$authWebController, 'logoutPost']);
$router->get('/recuperar-senha', [$authWebController, 'recuperarGet']);
$router->post('/recuperar-senha', [$authWebController, 'recuperarPost']);
$router->get('/redefinir-senha', [$authWebController, 'redefinirGet']);
$router->post('/redefinir-senha', [$authWebController, 'redefinirPost']);

$router->get('/conta', [$accountController, 'contaGet']);
$router->post('/conta', [$accountController, 'contaPost']);
$router->get('/meu-perfil/ministro', [$professionalController, 'meGet']);
$router->post('/meu-perfil/ministro', [$professionalController, 'mePost']);
$router->get('/perfil/profissional/{id}', [$professionalController, 'publicGet']);
$router->get('/meu-perfil/igreja', [$churchController, 'meGet']);
$router->post('/meu-perfil/igreja', [$churchController, 'mePost']);
$router->get('/perfil/igreja/{id}', [$churchController, 'publicGet']);
$router->get('/verificacao', [$verificationController, 'userGet']);
$router->post('/verificacao', [$verificationController, 'userPost']);
$router->get('/admin/verificacoes', [$verificationController, 'adminQueueGet']);
$router->get('/admin/revisao', [$verificationController, 'adminReviewGet']);
$router->post('/admin/verificacoes/decisao', [$verificationController, 'adminDecisionPost']);
$router->get('/denunciar', [$moderationController, 'denunciarGet']);
$router->post('/denunciar', [$moderationController, 'denunciarPost']);
$router->get('/admin/denuncias', [$moderationController, 'adminDenunciasGet']);
$router->post('/admin/usuarios/{id}/inativar', [$moderationController, 'adminInativarPost']);

$router->get('/api/health', [$apiController, 'health']);
$router->get('/api/search', [$apiController, 'search']);
$router->post('/api/chat/conversations', [$chatController, 'apiConversationsCreate']);
$router->get('/api/chat/conversations', [$chatController, 'apiConversationsList']);
$router->get('/api/chat/conversations/{id}', [$chatController, 'apiConversationGet']);
$router->post('/api/chat/conversations/{id}/messages', [$chatController, 'apiMessagePost']);
$router->post('/api/auth/register', [$apiController, 'register']);
$router->post('/api/auth/login', [$apiController, 'login']);
$router->post('/api/auth/logout', [$apiController, 'logout']);
$router->post('/api/auth/password/forgot', [$apiController, 'passwordForgot']);
$router->post('/api/auth/password/reset', [$apiController, 'passwordReset']);
$router->get('/api/privacy/export', [$apiController, 'privacyExport']);
$router->get('/api/profissionais/{id}', [$professionalController, 'publicApiGet']);
$router->get('/api/igrejas/{id}', [$churchController, 'publicApiGet']);
$router->post('/api/admin/verificacoes/{id}/decisao', [$verificationController, 'adminDecisionApiPost']);
$router->post('/api/reports', [$apiController, 'reportsPost']);

$response = $router->dispatch($request);
$response->send();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $showTrace = getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro</title></head><body style="font-family:system-ui;padding:1.5rem">';
    echo '<h1>Erro na aplicação</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    if ($showTrace) {
        echo '<pre style="overflow:auto;background:#f5f5f5;padding:1rem;font-size:12px">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<p><small>Defina a variável de ambiente <code>APP_DEBUG=1</code> no painel para ver detalhes técnicos.</small></p>';
    }
    echo '</body></html>';
}
