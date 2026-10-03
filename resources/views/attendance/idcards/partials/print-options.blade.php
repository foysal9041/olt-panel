{{-- Print / PDF options for ID cards (read by IdSheet.bindOptions). $copies: show the copies box. --}}
<div class="idp" id="idc-print-opts">
    <div class="idp-row">
        <span class="idp-k">{{ \App\Support\Ui::t('Print on') }}</span>
        <label class="idp-pill"><input type="radio" name="idp-paper" value="a4" data-opt="paper" checked> <span><i class="fas fa-file"></i> {{ \App\Support\Ui::t('A4 paper (5 a page)') }}</span></label>
        <label class="idp-pill"><input type="radio" name="idp-paper" value="pvc" data-opt="paper"> <span><i class="fas fa-id-card"></i> {{ \App\Support\Ui::t('PVC card printer') }}</span></label>
        @if ($copies ?? false)
            <select class="idp-sel" data-opt="copies" data-for="a4" title="Copies">
                @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}">{{ $n }} × {{ \App\Support\Ui::t('copies') }}</option>@endforeach
            </select>
        @endif
    </div>
    <div class="idp-row" data-for="pvc">
        <span class="idp-k">{{ \App\Support\Ui::t('Sides') }}</span>
        <select class="idp-sel" data-opt="sides">
            <option value="both">{{ \App\Support\Ui::t('Front and back') }}</option>
            <option value="front">{{ \App\Support\Ui::t('Front only') }}</option>
            <option value="back">{{ \App\Support\Ui::t('Back only') }}</option>
        </select>
        <select class="idp-sel" data-opt="order" data-for="pvc" data-when="both">
            <option value="paired">{{ \App\Support\Ui::t('Card by card: front, back (two-sided printer)') }}</option>
            <option value="grouped">{{ \App\Support\Ui::t('All fronts, then all backs (one-sided printer)') }}</option>
        </select>
        <select class="idp-sel" data-opt="orientation">
            <option value="portrait">{{ \App\Support\Ui::t('Page: portrait 54 × 85.6 mm') }}</option>
            <option value="landscape">{{ \App\Support\Ui::t('Page: landscape 85.6 × 54 mm (card turned)') }}</option>
        </select>
    </div>
    <div class="idp-row" data-for="a4">
        <label class="idp-check"><input type="checkbox" data-opt="fold" checked> <span><b>{{ \App\Support\Ui::t('Fold-over') }}</b> — {{ \App\Support\Ui::t('the back is printed upside down below the front: fold on the dark line and both sides read the right way up') }}</span></label>
    </div>
    <div class="idp-row" data-for="a4">
        <span class="idp-k" data-for="a4" data-when="fold">{{ \App\Support\Ui::t('Space at the fold') }}</span>
        <select class="idp-sel" data-opt="foldGap" data-for="a4" data-when="fold">
            @foreach ([4, 6, 8, 10, 12, 14] as $mm)<option value="{{ $mm }}" @selected($mm === 10)>{{ $mm }} mm</option>@endforeach
        </select>
        <span class="idp-k">{{ \App\Support\Ui::t('Bleed') }}</span>
        <select class="idp-sel" data-opt="bleed" title="{{ \App\Support\Ui::t('Extra colour round each card, so a die cut that is a little off leaves no white edge') }}">
            <option value="0">{{ \App\Support\Ui::t('None — dashed cut lines') }}</option>
            <option value="1" selected>1 mm — {{ \App\Support\Ui::t('for a die cutter') }}</option>
            <option value="1.5">1.5 mm — {{ \App\Support\Ui::t('for a die cutter') }}</option>
        </select>
    </div>
    <div class="idp-row">
        <label class="idp-check"><input type="checkbox" data-opt="mirror"> <span><b>{{ \App\Support\Ui::t('Mirror') }}</b> — {{ \App\Support\Ui::t('left-right reversed, for transfer paper or clear film') }}</span></label>
        <label class="idp-check" data-for="pvc"><input type="checkbox" data-opt="backFlip"> <span>{{ \App\Support\Ui::t('Turn the back upside down (if backs come out the wrong way up)') }}</span></label>
    </div>
</div>
