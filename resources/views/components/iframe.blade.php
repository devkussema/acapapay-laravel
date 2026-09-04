@props([
    'checkoutUrl',
    'height' => '700px',
    'width' => '100%',
    'id' => null,
])

@php
    // Cada instância tem um id próprio, para poderem coexistir dois checkouts na mesma página.
    $frameId = $id ?: 'acapapay-checkout-frame-' . \Illuminate\Support\Str::random(6);

    // Origens autorizadas a enviar postMessage: a do próprio checkout, o host do
    // AcapaPay, e quaisquer extras configuradas pela app.
    $allowedOrigins = [];

    foreach ([$checkoutUrl, config('acapapay.host'), config('acapapay.api_host')] as $candidate) {
        if (!$candidate) {
            continue;
        }

        $parts = parse_url($candidate);

        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            $allowedOrigins[] = $parts['scheme'] . '://' . $parts['host']
                . (!empty($parts['port']) ? ':' . $parts['port'] : '');
        }
    }

    $allowedOrigins = array_values(array_unique(array_merge(
        $allowedOrigins,
        (array) config('acapapay.iframe_allowed_origins', [])
    )));
@endphp

<div class="acapapay-iframe-container" style="position: relative; width: {{ $width }}; height: {{ $height }};">
    {{-- Iframe que vai carregar o Checkout do SSO --}}
    <iframe
        id="{{ $frameId }}"
        src="{{ $checkoutUrl }}"
        width="100%"
        height="100%"
        frameborder="0"
        allow="payment *; clipboard-write"
        referrerpolicy="strict-origin-when-cross-origin"
        style="border: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);"
    ></iframe>

    {{-- Lógica de Comunicação Iframe -> Parent (AcapaPay) --}}
    <script>
        (function () {
            var allowedOrigins = @json($allowedOrigins);

            window.addEventListener('message', function (event) {
                // Só aceitamos mensagens das origens do AcapaPay. Sem esta verificação,
                // qualquer página aberta noutro separador poderia forjar um
                // 'acapapay.success' e enganar a tua aplicação.
                if (allowedOrigins.length && allowedOrigins.indexOf(event.origin) === -1) {
                    return;
                }

                var data = event.data;

                if (!data || typeof data !== 'object') {
                    return;
                }

                if (data.event === 'acapapay.success') {
                    // Ex: window.addEventListener('acapapay-success', (e) => { ... })
                    window.dispatchEvent(new CustomEvent('acapapay-success', { detail: data }));
                }

                if (data.event === 'acapapay.cancel') {
                    window.dispatchEvent(new CustomEvent('acapapay-cancel', { detail: data }));
                }

                // Estados intermédios (ex: cripto à espera de confirmação na blockchain).
                if (data.event === 'acapapay.status') {
                    window.dispatchEvent(new CustomEvent('acapapay-status', { detail: data }));
                }
            });
        })();
    </script>
</div>
