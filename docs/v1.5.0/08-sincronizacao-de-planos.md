# AcapaPay Laravel SDK — Sincronização de Planos e Catálogo

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. O Catálogo de Planos do AcapaDev ID

Para aplicações que comercializam subscrições (SaaS), o AcapaDev ID centraliza a gestão de planos, periodicidades e preços. O SDK permite que defina os planos no código da sua aplicação Laravel e os sincronize com a plataforma central num único comando.

---

## 2. Configurar os Planos no `config/acapapay.php`

Abra o ficheiro `config/acapapay.php` e configure o array `plans`:

```php
return [
    // ...
    'plans' => [
        [
            'reference_code' => 'starter_monthly',
            'name'           => 'Plano Starter',
            'description'    => 'Acesso para até 3 utilizadores',
            'price'          => 15000.00,
            'currency'       => 'AOA',
            'billing_cycle'  => 'monthly', // 'monthly', 'yearly', ou 'quarterly'
            'features'       => [
                'Relatórios básicos',
                'Suporte por email',
            ],
            'is_active'      => true,
        ],
        [
            'reference_code' => 'pro_monthly',
            'name'           => 'Plano Professional',
            'description'    => 'Acesso para equipas completas',
            'price'          => 45000.00,
            'currency'       => 'AOA',
            'billing_cycle'  => 'monthly',
            'features'       => [
                'Relatórios avançados',
                'Exportação em Excel/PDF',
                'Suporte 24/7 dedicado',
            ],
            'is_active'      => true,
        ],
    ],
];
```

---

## 3. Sincronizar via Comando Artisan

Execute o comando Artisan fornecido pelo SDK:

```bash
php artisan acapapay:sync-plans
```

Este comando envia a definição de planos para o endpoint `PUT /v1/billing/plans` do AcapaDev ID via OAuth2 M2M, criando novos planos ou atualizando preços e descrições dos existentes.

---

## 4. Sincronização Programática

Também pode acionar a sincronização em tempo de execução através da Facade:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$updatedPlans = AcapaPay::syncPlans([
    [
        'reference_code' => 'enterprise_yearly',
        'name'           => 'Plano Enterprise Anual',
        'price'          => 500000.00,
        'currency'       => 'AOA',
        'billing_cycle'  => 'yearly',
    ]
]);
```
