<div component="dropdown"
     class="dropdown-container display-inline-item"
     id="export-menu">

    <div refs="dropdown@toggle"
         class="icon-list-item page-icon-list"
         aria-haspopup="true"
         aria-expanded="false"
         aria-label="{{ trans('entities.export') }}"
         data-shortcut="export"
         tabindex="0"
         title="{{ trans('entities.export') }}">
        <span>@icon('export')</span>
    </div>

    <ul refs="dropdown@menu" class="wide dropdown-menu" role="menu">
        <li><a href="{{ $entity->getUrl('/export/html') }}" target="_blank" class="label-item"><span>{{ trans('entities.export_html') }}</span><span>.html</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/pdf') }}" target="_blank" class="label-item"><span>{{ trans('entities.export_pdf') }}</span><span>.pdf</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/plaintext') }}" target="_blank" class="label-item"><span>{{ trans('entities.export_text') }}</span><span>.txt</span></a></li>
        <li><a href="{{ $entity->getUrl('/export/markdown') }}" target="_blank" class="label-item"><span>{{ trans('entities.export_md') }}</span><span>.md</span></a></li>
    </ul>

</div>
