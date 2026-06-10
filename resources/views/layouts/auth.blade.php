<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HYVE Workspace')</title>
    <meta name="description" content="Secure access to HYVE booking tools for professionals reserving workspaces and meeting rooms.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hyve-body min-h-screen text-stone-900 antialiased">
    @yield('content')
</body>
</html>
