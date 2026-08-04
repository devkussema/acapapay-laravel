# Guia de Sincronização de Planos (AcapaPay Laravel)

A partir da versão mais recente do SDK `acapapay-laravel`, é possível sincronizar automaticamente os planos de faturação da tua aplicação local com o portal SSO Acapadev. 

Esta sincronização utiliza autenticação M2M (Machine-to-Machine) e garante que todos os teus planos locais recebem o ID oficial de faturação (`acapapay_plan_id`) gerado pela AcapaPay.

## 1. Configurar a Model de Planos

O pacote precisa de saber qual é a classe (Model) que representa os planos na tua aplicação.
Para tal, deves publicar as configurações do pacote (se ainda não o fizeste):

```bash
php artisan vendor:publish --tag=acapapay-config
```

Depois, abre o ficheiro `config/acapapay.php` e define a chave `plan_model` com o namespace da tua Model:

```php
    /*
    |--------------------------------------------------------------------------
    | Model de Planos de Faturação
    |--------------------------------------------------------------------------
    */
    'plan_model' => \App\Models\Plan::class,
```

## 2. Preparar a Model

O comando de sincronização vai extrair os planos ativos da tua base de dados (`where('is_active', true)`) e enviá-os para a API.

Por predefinição, o pacote tenta ler os seguintes campos da tua model:
- `reference_code`
- `name`
- `description`
- `price`
- `currency` (Padrão: 'AOA')
- `billing_cycle` (Padrão: 'monthly')
- `features` (Padrão: [])

### Formatação Customizada (Opcional)
Se as colunas da tua base de dados tiverem nomes diferentes, podes implementar o método `toAcapaPayFormat()` na tua Model. O comando dará sempre prioridade a este método se ele existir:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    // ...

    public function toAcapaPayFormat(): array
    {
        return [
            'reference_code' => $this->codigo_referencia,
            'name' => $this->nome_plano,
            'description' => $this->descricao,
            'price' => $this->preco,
            'currency' => 'AOA',
            'billing_cycle' => $this->ciclo,
            'features' => json_decode($this->funcionalidades, true),
        ];
    }
}
```

## 3. Atualizar a Base de Dados

Quando a AcapaPay recebe os teus planos, ela regista-os internamente e devolve os IDs oficiais do portal (UUIDs). O pacote vai tentar guardar este UUID na coluna `acapapay_plan_id` da tua tabela.

Certifica-te que a tua tabela de planos tem esta coluna:

```php
Schema::table('plans', function (Blueprint $table) {
    $table->string('acapapay_plan_id')->nullable();
});
```

## 4. Executar a Sincronização

Após configurares a Model e garantires que tens as tuas credenciais `ACAPAPAY_CLIENT_ID` e `ACAPAPAY_CLIENT_SECRET` configuradas no ficheiro `.env`, basta executar o seguinte comando:

```bash
php artisan acapapay:sync-plans
```

**O que este comando faz:**
1. Obtém um Token OAuth (Client Credentials) diretamente do SSO.
2. Faz o upload da lista dos teus planos locais para a API da AcapaPay.
3. Atualiza localmente o `acapapay_plan_id` em todos os teus registos.
