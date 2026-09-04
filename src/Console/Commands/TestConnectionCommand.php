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

        // Usa o mesmo cliente (e a mesma cache de token) que o resto do SDK,
        // em vez de repetir aqui a lógica de autenticação.
        $client = app(\AcapaPay\Laravel\Http\AcapaPayClient::class);

        try {
            $client->token(fresh: true);
            $this->info('✔ Sucesso! Access Token obtido.');

            $this->line("\n[2/2] A testar endpoint de Ping (Validar Permissões da API)...");

            $client->ping();

            $this->info('✔ Sucesso! A API do SSO respondeu corretamente.');
            $this->newLine();
            $this->info('========================================================');
            $this->info('  🎉 Tudo configurado! Conexão AcapaPay estabelecida!   ');
            $this->info('========================================================');
            $this->newLine();

            return Command::SUCCESS;

        } catch (\AcapaPay\Laravel\Exceptions\AuthenticationException $e) {
            $this->error('✘ FALHA NA AUTENTICAÇÃO');
            $this->line('O SSO rejeitou as credenciais. Confirma ACAPAPAY_CLIENT_ID e ACAPAPAY_CLIENT_SECRET.');
            $this->error($e->getMessage());
            return Command::FAILURE;
        } catch (\AcapaPay\Laravel\Exceptions\ConnectionException $e) {
            $this->error('✘ ERRO DE REDE');
            $this->error($e->getMessage());
            $this->line('Poderá ser um problema de DNS, Servidor SSO offline ou certificado SSL inválido se for local.');
            return Command::FAILURE;
        } catch (\AcapaPay\Laravel\Exceptions\ApiException $e) {
            $this->error('✘ FALHA NO PING DA API (HTTP ' . $e->status() . ')');
            $this->line('As credenciais foram aceites, mas o endpoint recusou o pedido.');
            $this->error($e->apiError() ?: $e->body());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('✘ ERRO INESPERADO');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
