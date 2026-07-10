<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $pageMeta = $meta ?? config('hyve.meta', [
            'title' => 'HYVE Workspace',
            'description' => 'HYVE offers polished workspaces and meeting rooms for professionals who want to work well and connect well.',
        ]);
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageMeta['title'] }}</title>
    <meta name="description" content="{{ $pageMeta['description'] }}">
    <meta name="theme-color" content="#11342c">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logohyve.jpg') }}">
    <link rel="shortcut icon" href="{{ asset('images/logohyve.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logohyve.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hyve-body">
    @yield('content')
</body>
</html>
