<div class="guide-header">
    <div class="guide-header-title">
        <span class="guide-icon">⚙️</span>
        <div>
            <h2>Panduan Superadmin</h2>
            <p>Pelajari langkah utama untuk mengelola pengguna, skema sertifikasi, pemantauan sistem, dan audit trail.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Mulai dari Sini</h3>
    <div class="step-cards-grid">
        <div class="step-card">
            <span class="step-badge">Langkah 1</span>
            <h4>Pengelolaan Pengguna &amp; Role</h4>
            <p>Tambahkan, edit, atau nonaktifkan akun pengguna serta atur penetapan role pengguna di dalam portal.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 2</span>
            <h4>Pengelolaan Skema &amp; Master Data</h4>
            <p>Kelola katalog skema sertifikasi yang aktif, kelompok produk SNI, serta standar acuan yang berlaku.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 3</span>
            <h4>Pengelolaan Form Dinamis</h4>
            <p>Atur kolom formulir, tipe input, serta daftar persyaratan dokumen yang dibutuhkan pada setiap skema.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 4</span>
            <h4>Pemantauan Seluruh Permohonan</h4>
            <p>Pantau perkembangan seluruh permohonan sertifikasi dari tahap verifikasi administrasi hingga selesai.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 5</span>
            <h4>Pengelolaan Konfigurasi</h4>
            <p>Kelola pengaturan aplikasi serta parameter referensi sistem portal yang tersedia.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 6</span>
            <h4>Audit Trail &amp; Monitoring</h4>
            <p>Pantau log aktivitas sistem, riwayat login pengguna, serta rekam jejak perubahan data demi keamanan portal.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Tindakan Cepat</h3>
    <div class="quick-actions-grid">
        <a href="{{ route('superadmin.users.index') }}" class="quick-action-btn">
            <strong>Kelola Pengguna</strong>
            <span>Atur akun pengguna dan hak akses peran</span>
        </a>
        <a href="{{ route('superadmin.schemes.index') }}" class="quick-action-btn">
            <strong>Kelola Skema</strong>
            <span>Konfigurasi katalog skema dan form dinamis</span>
        </a>
        <a href="{{ route('internal.applications.index') }}" class="quick-action-btn">
            <strong>Review Permohonan</strong>
            <span>Pantau antrean verifikasi administrasi</span>
        </a>
        <a href="{{ route('finance.index') }}" class="quick-action-btn">
            <strong>Invoice &amp; Pembayaran</strong>
            <span>Pantau tagihan dan konfirmasi pembayaran</span>
        </a>
        <a href="{{ route('audit.index') }}" class="quick-action-btn">
            <strong>Audit</strong>
            <span>Pantau penugasan dan evaluasi audit</span>
        </a>
        <a href="{{ route('technical.index') }}" class="quick-action-btn">
            <strong>Sertifikat</strong>
            <span>Pantau proses tinjauan teknis dan penerbitan</span>
        </a>
        <a href="{{ route('superadmin.audit-trail.index') }}" class="quick-action-btn">
            <strong>Audit Trail</strong>
            <span>Pantau log aktivitas dan keamanan sistem</span>
        </a>
    </div>
</div>

<div class="guide-section">
    <h3>Kapan Saya Perlu Bertindak?</h3>
    <div class="status-action-list">
        <div class="status-action-item">
            <span class="status-label badge-success">Pengguna Baru / Role</span>
            <p>Atur penetapan peran bagi akun pengguna baru yang terdaftar di dalam portal.</p>
        </div>
        <div class="status-action-item">
            <span class="status-label badge-success">Konfigurasi Skema</span>
            <p>Kelola perubahan kolom isian atau syarat dokumen pada skema sertifikasi yang berlaku.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Bantuan Cepat</h3>
    <div class="faq-accordion">
        <details class="faq-item">
            <summary>Mengapa tombol tertentu tidak muncul?</summary>
            <p>Superadmin memiliki akses ke seluruh modul utama untuk kemudahan pemantauan dan administrasi sistem.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa data tidak dapat diedit?</summary>
            <p>Beberapa data riwayat audit atau sertifikat yang telah selesai diterbitkan dikunci demi menjaga kepatuhan rekam jejak.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa order tidak muncul?</summary>
            <p>Gunakan filter pencarian atau reset filter status pada daftar permohonan untuk menampilkan seluruh data.</p>
        </details>
        <details class="faq-item">
            <summary>Apa arti status yang sedang tampil?</summary>
            <p>Status menampilkan tahapan terkini dari setiap permohonan yang ada di seluruh modul.</p>
        </details>
        <details class="faq-item">
            <summary>Siapa yang harus dihubungi jika ada kendala?</summary>
            <p>Sebagai Superadmin, Anda berwenang melakukan pengelolaan sistem serta konfigurasi akun di portal.</p>
        </details>
    </div>
</div>
