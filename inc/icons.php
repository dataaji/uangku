<?php
// Ikon SVG sederhana (stroke). Pakai: icon('home', 20)
function icon($name, $size = 22, $color = 'currentColor', $sw = 1.8) {
    $paths = [
        'home'    => '<path d="M3 11l9-7 9 7M5 10v9a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1v-9"/>',
        'list'    => '<path d="M8 6h12M8 12h12M8 18h12"/>',
        'cal'     => '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M4 9h16M9 3v4M15 3v4"/>',
        'budget'  => '<path d="M21 12a9 9 0 11-9-9v9z"/><path d="M12 3a9 9 0 019 9h-9z"/>',
        'savings' => '<path d="M3 12a7 5 0 0014 0 7 5 0 00-14 0z"/><path d="M17 10c1.2.3 2 1 2 2s-.8 1.7-2 2M7 8V6a3 3 0 016 0v1.2"/>',
        'bill'    => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'task'    => '<path d="M9 11l2 2 4-4"/><rect x="4" y="3" width="16" height="18" rx="2"/>',
        'settings'=> '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        'bell'    => '<path d="M18 8a6 6 0 10-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'check'   => '<path d="M5 12l4.5 4.5L19 7"/>',
        'chevR'   => '<path d="M9 6l6 6-6 6"/>',
        'chevL'   => '<path d="M15 6l-6 6 6 6"/>',
        'arrowUp' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrowDn' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
        'trash'   => '<path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13"/>',
        'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
        'more'    => '<path d="M5 6h.01M5 12h.01M5 18h.01M10 6h10M10 12h10M10 18h10"/>',
        'moon'    => '<path d="M21 12.8A8 8 0 1111 3a6.5 6.5 0 0010 9.8z"/>',
        'lock'    => '<path d="M6 10V7a6 6 0 0112 0v3M5 10h14a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1v-9a1 1 0 011-1z"/>',
        'repeat'  => '<path d="M4 10a8 8 0 0113-6l3 3M20 14a8 8 0 01-13 6l-3-3M17 4h3v3M7 20H4v-3"/>',
        'export'  => '<path d="M12 3v12M8 7l4-4 4 4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>',
        'chart'   => '<path d="M3 3v18h18"/><path d="M7 14l3-4 3 3 4-6"/>',
        'wallet'  => '<path d="M3 7a2 2 0 012-2h12a2 2 0 012 2H3z"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 13h.01"/>',
    ];
    $p = $paths[$name] ?? '';
    return "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"$color\" stroke-width=\"$sw\" stroke-linecap=\"round\" stroke-linejoin=\"round\">$p</svg>";
}
