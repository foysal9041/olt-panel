{{-- Draw (or upload) the authorized signature; used on the ID card page and the ID Cards page. --}}
<div class="modal fade" id="sig-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('attendance.idcards.signature') }}" enctype="multipart/form-data" class="modal-content" id="sig-form">
            @csrf @method('PUT')
            <input type="hidden" name="signature_data" id="sig-data">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-signature text-primary"></i> Authorized signature</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Sign inside the box with the mouse, a finger or a pen — or upload a photo of the signature on white paper.</p>
                <div style="position: relative; border: 2px dashed #cbd5e1; border-radius: .75rem; background: #fff; touch-action: none;">
                    <canvas id="sig-pad" width="900" height="300" style="width: 100%; height: auto; display: block; cursor: crosshair;"></canvas>
                    <div style="position: absolute; left: 8%; right: 8%; bottom: 22%; border-bottom: 1px solid #e2e8f0; pointer-events: none;"></div>
                </div>
                <div class="d-flex flex-wrap align-items-center mt-2" style="gap: .5rem">
                    <button type="button" class="btn btn-sm btn-light border" id="sig-clear"><i class="fas fa-eraser"></i> Clear</button>
                    <span class="text-muted small">or</span>
                    <input type="file" name="signature_file" accept="image/png,image/jpeg" class="form-control-file" style="max-width: 18rem">
                </div>
                @if (! empty($hasSignature))
                    <div class="custom-control custom-checkbox mt-3">
                        <input type="checkbox" class="custom-control-input" id="sig-remove" name="remove_signature" value="1">
                        <label class="custom-control-label font-weight-normal" for="sig-remove">Remove the current signature</label>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Save signature</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var pad = document.getElementById('sig-pad'), ctx = pad.getContext('2d'), drawing = false, drawn = false, last = null;
    ctx.lineWidth = 5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
    function at(e) { var r = pad.getBoundingClientRect(); return { x: (e.clientX - r.left) * pad.width / r.width, y: (e.clientY - r.top) * pad.height / r.height }; }
    pad.addEventListener('pointerdown', function (e) { drawing = true; last = at(e); pad.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.arc(last.x, last.y, 2.2, 0, Math.PI * 2); ctx.fillStyle = '#0f172a'; ctx.fill(); drawn = true; });
    pad.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = at(e), mid = { x: (last.x + p.x) / 2, y: (last.y + p.y) / 2 };
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.quadraticCurveTo(last.x, last.y, mid.x, mid.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        last = p; drawn = true;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) { pad.addEventListener(ev, function () { drawing = false; }); });
    document.getElementById('sig-clear').addEventListener('click', function () { ctx.clearRect(0, 0, pad.width, pad.height); drawn = false; });
    document.getElementById('sig-form').addEventListener('submit', function () {
        if (drawn) {
            // White paper behind the ink, the server turns it into clear ink.
            var out = document.createElement('canvas'); out.width = pad.width; out.height = pad.height;
            var o = out.getContext('2d'); o.fillStyle = '#fff'; o.fillRect(0, 0, out.width, out.height); o.drawImage(pad, 0, 0);
            document.getElementById('sig-data').value = out.toDataURL('image/png');
        }
    });
})();
</script>
