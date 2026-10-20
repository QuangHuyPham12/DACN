@props(['name' => 'grid'])
@php
$paths = [
'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
'user' => '<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/>',
'tag' => '<path d="m21 13-8 8-11-11V2h8l11 11Z"/><circle cx="7" cy="7" r="1"/>',
'sliders' => '<path d="M3 6h18M3 12h18M3 18h18"/><circle cx="8" cy="6" r="2" fill="currentColor"/><circle cx="16" cy="12" r="2" fill="currentColor"/><circle cx="10" cy="18" r="2" fill="currentColor"/>',
'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
'box' => '<path d="m3 7 9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v10M7 5l10 4"/>',
'cart' => '<path d="M3 3h2l3 12h11l3-9H6M9 19h.01M18 19h.01"/><circle cx="9" cy="19" r="1"/><circle cx="18" cy="19" r="1"/>',
'bag' => '<path d="M5 7h14l1 14H4L5 7ZM9 7V5a3 3 0 0 1 6 0v2"/>',
'users' => '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 5a5 5 0 0 1 3 5"/>',
'truck' => '<path d="M2 5h12v12H2zM14 9h4l4 5v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
'chart' => '<path d="M4 3v18h17M9 16v-5M14 16V7M19 16V4"/>',
'layers' => '<path d="m2 8 10-5 10 5-10 5-10-5Zm0 5 10 5 10-5M2 18l10 5 10-5"/>',
'money' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/>',
'plus' => '<path d="M12 5v14M5 12h14"/>',
'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
'logout' => '<path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4"/>',
'shield' => '<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/>',
'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
'check' => '<path d="m5 12 4 4L19 6"/>',
'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" aria-hidden="true">{!! $paths[$name] ?? $paths['grid'] !!}</svg>
