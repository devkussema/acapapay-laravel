<?php

namespace AcapaPay\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestConnectionCommand extends Command
{
    /**
     * O nome e assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'acapapay:test-connection';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Testa a ligação (OAuth2 e API) entre esta Aplicação Satélite e o SSO central do AcapaPay.';

    /**
     * Executa o comando.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando Teste de Ligação M2M AcapaPay...');
        
        $host = config('acapapay.host');
        $apiHost = config('acapapay.api_host');
        $clientId = config('acapapay.client_id');
        $clientSecret = config('acapapay.client_secret');

        if (!$host || !$apiHost || !$clientId || !$clientSecret) {
            $this->error('ERRO: Variáveis de ambiente incompletas!');
            $this->line('Verifique se ACAPAPAY_HOST, ACAPAPAY_API_HOST, ACAPAPAY_CLIENT_ID e ACAPAPAY_CLIENT_SECRET estão definidos no seu .env');
            return Command::FAILURE;
        }

        $this->line("- Host Configurado: <comment>$host</comment>");
        $this->line("- Client ID: <comment>$clientId</comment>");

        $this->line("\n[1/2] A tentar autenticação OAuth2 (Client Credentials)...");

        try {
            $authReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->asForm()->post($host . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$authReq->successful()) {
                $this->error('✘ FALHA NA AUTENTICAÇÃO');
                $this->line('O SSO rejeitou as credenciais. Erro do Servidor:');
                $this->error($authReq->body());
                return Command::FAILURE;
            }

            $token = $authReq->json('access_token');
            $this->info('✔ Sucesso! Access Token obtido.');

            $this->line("\n[2/2] A testar endpoint de Ping (Validar Permissões da API)...");

            $pingReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->withToken($token)->get($apiHost . '/v1/ping');

            if (!$pingReq->successful()) {
                $this->error('✘ FALHA NO PING DA API');
                $this->line('As credenciais foram aceites, mas o endpoint rejeitou o token.');
                $this->error($pingReq->body());
                return Command::FAILURE;
            }

            $this->info('✔ Sucesso! A API do SSO respondeu corretamente.');
            $this->newLine();
            $this->info('========================================================');
            $this->info('  🎉 Tudo configurado! Conexão AcapaPay estabelecida!   ');
            $this->info('========================================================');
            $this->newLine();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✘ ERRO DE REDE / EXCEÇÃO CATASTRÓFICA');
            $this->error($e->getMessage());
            $this->line('Poderá ser um problema de DNS, Servidor SSO offline ou certificado SSL inválido se for local.');
            return Command::FAILURE;
        }
    }
}
