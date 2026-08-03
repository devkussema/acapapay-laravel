@props(['checkoutUrl', 'height' => '700px', 'width' => '100%'])

<div class="acapapay-iframe-container" style="position: relative; width: {{ $width }}; height: {{ $height }};">
    {{-- Iframe que vai carregar o Checkout do SSO --}}
    <iframe 
        id="acapapay-checkout-frame"
        src="{{ $checkoutUrl }}" 
        width="100%" 
        height="100%" 
        frameborder="0" 
        allowpaymentrequest
        style="border: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);"
    ></iframe>

    {{-- Lógica de Comunicação Iframe -> Parent (AcapaPay) --}}
    <script>
        window.addEventListener('message', function(event) {
            // Em produção, seria aconselhável validar event.origin === 'https://id.acapadev.com'
            // Mas permitimos para testes locais ou domínios de staging dinâmicos
            
            const data = event.data;
            
            if (data && typeof data === 'object') {
                if (data.event === 'acapapay.success') {
                    // Dispara um evento personalizado no browser para a app cliente apanhar, ex:
                    // window.addEventListener('acapapay-success', (e) => { fechaIframe(); mostraFeedback(); })
                    window.dispatchEvent(new CustomEvent('acapapay-success', { detail: data }));
                    console.log('AcapaPay SDK: Pagamento efetuado com sucesso via Iframe!', data);
                }
                
                if (data.event === 'acapapay.cancel') {
                    window.dispatchEvent(new CustomEvent('acapapay-cancel', { detail: data }));
                    console.log('AcapaPay SDK: Utilizador cancelou o pagamento.');
                }
            }
        });
    </script>
</div>
