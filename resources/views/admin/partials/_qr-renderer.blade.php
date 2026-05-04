{{--
    Shared QR Code Renderer
    Usage: @include('admin.partials._qr-renderer', ['selector' => '.rack-qrcode', 'size' => 110])
    
    Elements must have data-code="..." attribute with the QR value.
    Default selector: .rack-qrcode
    Default size: 110
--}}
@once
<script src="{{ asset('js/vendor/qrcode.min.js') }}" defer></script>
@endonce
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selector = @json($selector ?? '.rack-qrcode');
    var size = {{ $size ?? 110 }};
    document.querySelectorAll(selector).forEach(function(el) {
        var code = el.getAttribute('data-code');
        if (code) {
            el.innerHTML = '';
            new QRCode(el, {
                text: code,
                width: size,
                height: size,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
});
</script>
