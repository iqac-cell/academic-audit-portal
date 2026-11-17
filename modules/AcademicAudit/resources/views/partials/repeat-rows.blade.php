{{-- Add / remove rows in a table. Markup: <div data-repeat data-next="N"> table with <tbody> rows (<tr data-row>),
     <template> holding one row whose names use __i__, and a <button data-add-row>. Remove buttons carry data-remove-row.
     Cells with class "sn" are renumbered. Include once per page. --}}
@once
@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add-row]');
    var del = e.target.closest('[data-remove-row]');
    if (!add && !del) { return; }
    var box = (add || del).closest('[data-repeat]');
    if (!box) { return; }
    var body = box.querySelector('tbody');
    var renumber = function () {
        body.querySelectorAll('tr[data-row]').forEach(function (tr, i) {
            var sn = tr.querySelector('.sn');
            if (sn) { sn.textContent = i + 1; }
        });
    };
    var addRow = function () {
        var i = parseInt(box.dataset.next || '0', 10);
        box.dataset.next = i + 1;
        var html = box.querySelector('template').innerHTML.replace(/__i__/g, i);
        body.insertAdjacentHTML('beforeend', html);
        renumber();
        var first = body.lastElementChild.querySelector('input, select, textarea');
        if (first && add) { first.focus(); }
    };
    if (add) {
        e.preventDefault();
        addRow();
    } else {
        e.preventDefault();
        del.closest('tr').remove();
        if (!body.querySelector('tr[data-row]')) { addRow(); }
        renumber();
    }
});
</script>
@endpush
@endonce
