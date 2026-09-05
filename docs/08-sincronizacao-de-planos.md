# 8. Sincronização de Planos de Faturação

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

O AcapaPay mantém um catálogo centralizado de planos. Para que a tua app possa criar sessões de checkout com um `plan_reference_code` válido, os teus planos locais têm de estar registados no servidor central. O comando `acapapay:sync-plans` automatiza este processo, usando autenticação M2M (Machine-to-Machine).

## 1. Configurar a Model de Planos

Publica a configuração do pacote, se ainda não o fizeste:

```bash
php artisan vendor:publish --tag="acapapay-config"
```

No ficheiro `config/acapapay.php`, define a classe (Model) que representa os teus planos:

```php
'plan_model' => \App\Models\Plan::class,
```

## 2. Preparar a tabela

Adiciona a coluna `acapapay_plan_id`, onde o UUID oficial devolvido pelo servidor central será guardado:

```php
// Numa nova migration:
Schema::table('plans', function (Blueprint $table) {
    $table->string('acapapay_plan_id')->nullable();
});
```

## 3. Campos esperados (padrão)

O comando extrai os planos ativos (`where('is_active', true)`) e lê, por padrão, os seguintes campos da tua Model:

| Campo | Obrigatório | Padrão |
|---|---|---|
| `reference_code` | Sim | — |
| `name` | Sim | — |
| `price` | Sim | — |
| `description` | Não | — |
| `currency` | Não | `'AOA'` |
| `billing_cycle` | Não | `'monthly'` |
| `features` | Não | `[]` |

### Formatação customizada (opcional)

Se as colunas da tua base de dados tiverem nomes diferentes, implementa `toAcapaPayFormat()` na tua Model. O comando dá sempre prioridade a este método, se existir:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public function toAcapaPayFormat(): array
    {
        return [
            'reference_code' => $this->codigo_referencia,
            'name'           => $this->nome_plano,
            'description'    => $this->descricao,
            'price'          => $this->preco,
            'currency'       => 'AOA',
            'billing_cycle'  => $this->ciclo,
            'features'       => json_decode($this->funcionalidades, true),
        ];
    }
}
```

## 4. Executar

Com `ACAPAPAY_CLIENT_ID`/`ACAPAPAY_CLIENT_SECRET` já configurados:

```bash
php artisan acapapay:sync-plans
```

**O que este comando faz internamente:**
1. Obtém um token OAuth M2M do SSO (Client Credentials).
2. Lê todos os planos com `is_active = true` da tua base de dados.
3. Transforma cada plano para o formato AcapaPay (`toAcapaPayFormat()` ou os campos padrão acima).
4. Envia a lista via `PUT /v1/billing/plans` para o servidor central.
5. O servidor devolve os UUIDs oficiais atribuídos a cada plano.
6. O comando atualiza a coluna `acapapay_plan_id` localmente, um `UPDATE` por plano.

> [!TIP]
> Corre o sync sempre que adicionares novos planos, alterares preços, ou alterares campos de qualquer plano já sincronizado anteriormente.

---

**A seguir:** [09-referencia-rapida.md](09-referencia-rapida.md) — tabela de referência com todos os métodos, comandos e eventos, num só sítio.
