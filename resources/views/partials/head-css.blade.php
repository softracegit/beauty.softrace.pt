<!-- Favicons -->
<link href="{{ asset('template/img/favicon.png') }}" rel="icon">
<link href="{{ asset('template/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

<!-- Google Fonts - Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Vendor CSS Files -->
<link href="{{ asset('template/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/phosphor-icons/phosphor-icons.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/lucide-icons/lucide.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/simple-datatables/style.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/quill/quill.snow.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/quill/quill.bubble.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/choices.js/choices.min.css') }}" rel="stylesheet">
<link href="{{ asset('template/vendor/flatpickr/flatpickr.min.css') }}" rel="stylesheet">

<!-- Template Main CSS File -->
<link href="{{ asset('template/css/main.css') }}?v={{ file_exists(public_path('template/css/main.css')) ? filemtime(public_path('template/css/main.css')) : time() }}" rel="stylesheet">
<link href="{{ asset('template/css/cash-register.css') }}?v={{ file_exists(public_path('template/css/cash-register.css')) ? filemtime(public_path('template/css/cash-register.css')) : time() }}" rel="stylesheet">
