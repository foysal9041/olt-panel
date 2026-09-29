{{-- Month picker + tabs shared by the ledger and summary pages. Expects $month, $active. --}}
<div class="d-flex align-items-center flex-wrap mb-3" style="gap:.5rem">
    <div class="btn-group">
        <a href="{{ route('accounts.reports.ledger', request()->except('print') + ['month' => $month->format('Y-m')]) }}"
           class="btn btn-sm {{ $active === 'ledger' ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fas fa-list"></i> খাত-ভিত্তিক হিসাব</a>
        <a href="{{ route('accounts.reports.summary', ['month' => $month->format('Y-m')]) }}"
           class="btn btn-sm {{ $active === 'summary' ? 'btn-primary' : 'btn-outline-secondary' }}"><i class="fas fa-chart-bar"></i> মোট খরচ ও ইনকাম</a>
    </div>
    <form method="GET" class="d-flex align-items-center ml-auto" style="gap:.4rem">
        @foreach (request()->except(['month', 'print']) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <a href="{{ request()->fullUrlWithQuery(['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-light"><i class="fas fa-chevron-left"></i></a>
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
        <a href="{{ request()->fullUrlWithQuery(['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-light"><i class="fas fa-chevron-right"></i></a>
    </form>
</div>
