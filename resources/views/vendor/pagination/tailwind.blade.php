@if ($paginator->hasPages())
    <nav style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;">
        <p style="font-size:12px;color:#64748b;margin:0;">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>
        <div style="display:flex;gap:4px;">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span style="padding:6px 10px;border-radius:6px;font-size:12px;color:#cbd5e1;background:#f8fafc;cursor:default;">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" style="padding:6px 10px;border-radius:6px;font-size:12px;color:#0f172a;background:#e2e8f0;text-decoration:none;">&lsaquo;</a>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span style="padding:6px 10px;border-radius:6px;font-size:12px;color:#94a3b8;">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span style="padding:6px 10px;border-radius:6px;font-size:12px;color:#fff;background:#1b3a6b;font-weight:600;">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" style="padding:6px 10px;border-radius:6px;font-size:12px;color:#0f172a;background:#e2e8f0;text-decoration:none;">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" style="padding:6px 10px;border-radius:6px;font-size:12px;color:#0f172a;background:#e2e8f0;text-decoration:none;">&rsaquo;</a>
            @else
                <span style="padding:6px 10px;border-radius:6px;font-size:12px;color:#cbd5e1;background:#f8fafc;cursor:default;">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
