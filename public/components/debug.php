<?php if ($data): ?>
<div class="mixxx-container rounded-lg mb-6">
    <div class="border-b-2 border-cyan-600">
        <nav class="flex -mb-px">
            <button onclick="showTab('metadata')" id="metadataTab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-cyan-500 text-cyan-400 bg-gray-800">
                <i class="fas fa-info-circle"></i> Database Metadata
            </button>
        </nav>
    </div>

    <div id="metadataContent" class="tab-content p-6">
        <h2 class="text-2xl font-bold deck-title mb-4 flex items-center gap-2">
            <i class="fas fa-database"></i>
            <span>Database Metadata</span>
        </h2>
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
            <pre class="text-sm text-cyan-300 overflow-x-auto"><?= json_encode($data['metadata'], JSON_PRETTY_PRINT) ?></pre>
        </div>
    </div>
</div>
<?php endif; ?>
