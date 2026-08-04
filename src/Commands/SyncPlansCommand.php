<?php

namespace AcapaPay\Laravel\Commands;

use Illuminate\Console\Command;
use AcapaPay\Laravel\Facades\AcapaPay;

class SyncPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acapapay:sync-plans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza os planos de faturação locais da aplicação com o SSO Acapadev usando o SDK AcapaPay.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronização de planos com a AcapaPay...');

        $modelClass = config('acapapay.plan_model');

        if (!$modelClass || !class_exists($modelClass)) {
            $this->error('A model de planos não está configurada ou não existe. Verifique a chave "plan_model" em config/acapapay.php.');
            return 1;
        }

        $plansCollection = $modelClass::where('is_active', true)->get();

        $plans = $plansCollection->map(function ($plan) {
            // Se a model implementar um método customizado, usamo-lo
            if (method_exists($plan, 'toAcapaPayFormat')) {
                return $plan->toAcapaPayFormat();
            }

            // Fallback para colunas padrão esperadas
            return [
                'reference_code' => $plan->reference_code ?? null,
                'name' => $plan->name ?? null,
                'description' => $plan->description ?? null,
                'price' => $plan->price ?? 0,
                'currency' => $plan->currency ?? 'AOA',
                'billing_cycle' => $plan->billing_cycle ?? 'monthly',
                'features' => $plan->features ?? [],
            ];
        })->toArray();

        if (empty($plans)) {
            $this->warn('Nenhum plano ativo encontrado localmente. Sincronizando lista vazia...');
            $plans = [];
        }

        try {
            $syncedPlans = AcapaPay::syncPlans($plans);
            
            $this->info('Planos sincronizados com sucesso no AcapaPay!');

            if ($syncedPlans && is_array($syncedPlans)) {
                $count = 0;
                foreach ($syncedPlans as $p) {
                    if (isset($p['reference_code']) && isset($p['id'])) {
                        $modelClass::where('reference_code', $p['reference_code'])
                            ->update(['acapapay_plan_id' => $p['id']]);
                        $count++;
                    }
                }
                $this->info("IDs AcapaPay atualizados localmente em {$count} planos.");
            }
            
            return 0;

        } catch (\Exception $e) {
            $this->error('Erro durante a sincronização: ' . $e->getMessage());
            return 1;
        }
    }
}
