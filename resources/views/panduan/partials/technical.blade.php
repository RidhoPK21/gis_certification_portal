<div class="guide-header">
    <div class="guide-header-title">
        <span class="guide-icon">📜</span>
        <div>
            <h2>Panduan Tim Teknis</h2>
            <p>Pelajari langkah utama untuk meninjau permohonan, memutuskan Setujui/Tolak, menerbitkan Surat Tugas auditor, hingga menerbitkan dokumen sertifikat.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Mulai dari Sini</h3>
    <div class="step-cards-grid">
        <div class="step-card">
            <span class="step-badge">Langkah 1</span>
            <h4>Meninjau Permohonan</h4>
            <p>Buka permohonan yang diteruskan Admin. Seluruh data isian dan dokumen unggahan Klien tersedia di halaman ini.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 2</span>
            <h4>Menentukan Tim Auditor &amp; Panelis</h4>
            <p>Pilih tim auditor beserta lingkup penugasannya — minimal satu Lead Auditor — dan tentukan panelis. Tim ini tercetak pada PDF tinjauan.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 3</span>
            <h4>Memutuskan Setujui atau Tolak</h4>
            <p>Keputusan akhir ada di tangan Anda. Menyetujui akan membuat PDF tinjauan dan meneruskan order ke Finance. Anda juga dapat meminta revisi ke Klien.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 4</span>
            <h4>Menerbitkan Surat Tugas</h4>
            <p>Setelah pembayaran lunas, buka menu Penugasan &amp; Surat Tugas. Ganti auditor bila perlu, unggah tanda tangan berstempel, lalu terbitkan surat per tahap audit.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 5</span>
            <h4>Memantau Pelaksanaan Audit</h4>
            <p>Pantau siapa yang bertugas, PDF tinjauan yang sudah terbit, dan status Surat Tugas tiap tahap dari satu halaman.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 6</span>
            <h4>Menerbitkan Sertifikat</h4>
            <p>Setelah audit selesai tanpa temuan terbuka, siapkan draft, jalankan review, lalu terbitkan dokumen akhir sertifikat.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Tindakan Cepat</h3>
    <div class="quick-actions-grid">
        <a href="{{ route('technical.reviews.index') }}" class="quick-action-btn">
            <strong>Buka Antrean Tinjauan Teknis</strong>
            <span>Tinjau permohonan dan ambil keputusan Setujui/Tolak</span>
        </a>
        <a href="{{ route('technical.assignments.index') }}" class="quick-action-btn">
            <strong>Penugasan &amp; Surat Tugas</strong>
            <span>Terbitkan Surat Tugas agar auditor dapat mulai bekerja</span>
        </a>
        <a href="{{ route('technical.index') }}" class="quick-action-btn">
            <strong>Buka Proses Sertifikat</strong>
            <span>Kelola pemeriksaan akhir dan penerbitan sertifikat</span>
        </a>
    </div>
</div>

<div class="guide-section">
    <h3>Kapan Saya Perlu Bertindak?</h3>
    <div class="status-action-list">
        <div class="status-action-item">
            <span class="status-label badge-info">Tinjauan Teknis</span>
            <p>Isi tinjauan teknis, tentukan tim auditor, lalu putuskan Setujui, Tolak, atau minta revisi ke Klien.</p>
        </div>
        <div class="status-action-item">
            <span class="status-label badge-info">Pembayaran Selesai</span>
            <p>Terbitkan Surat Tugas untuk tahap audit yang akan dijalankan. Auditor belum dapat bekerja sebelum suratnya terbit.</p>
        </div>
        <div class="status-action-item">
            <span class="status-label badge-success">Review Sertifikat</span>
            <p>Periksa kelengkapan hasil audit dan siapkan draft sertifikat untuk permohonan tersebut.</p>
        </div>
        <div class="status-action-item">
            <span class="status-label badge-success">Sertifikat Selesai</span>
            <p>Sertifikat telah selesai diterbitkan dan dapat diakses oleh Klien pada portal.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Bantuan Cepat</h3>
    <div class="faq-accordion">
        <details class="faq-item">
            <summary>Mengapa tombol tertentu tidak muncul?</summary>
            <p>Tombol penerbitan sertifikat hanya aktif apabila seluruh temuan audit telah selesai diverifikasi oleh Auditor.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa tombol Setujui tidak aktif?</summary>
            <p>Menyetujui memerlukan tiga hal: tinjauan teknis sudah disimpan, tim auditor sudah ditentukan dengan minimal satu Lead Auditor, dan tidak ada item revisi yang masih terbuka.</p>
        </details>
        <details class="faq-item">
            <summary>Apakah tanda tangan berstempel juga dipakai di PDF tinjauan?</summary>
            <p>Tidak. Unggahan tanda tangan berstempel hanya membubuhi Surat Tugas. PDF Tinjauan Permohonan tetap memakai tanda tangan elektronik dari profil akun Anda.</p>
        </details>
        <details class="faq-item">
            <summary>Bisakah auditor diganti setelah tinjauan?</summary>
            <p>Bisa. Pada halaman Penugasan &amp; Surat Tugas, tim dapat diubah dan tiap tahap boleh punya tim berbeda. Surat Tugas yang sudah terbit tidak ikut berubah — terbitkan ulang bila perlu.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa data tidak dapat diedit?</summary>
            <p>Sertifikat yang telah diterbitkan secara final dikunci dari perubahan agar data tetap akurat.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa order tidak muncul?</summary>
            <p>Permohonan baru akan masuk ke antrean Tim Teknis setelah tahapan evaluasi dari Auditor dinyatakan selesai.</p>
        </details>
        <details class="faq-item">
            <summary>Apa arti status yang sedang tampil?</summary>
            <p>Status menampilkan apakah permohonan sedang dalam proses pemeriksaan akhir teknis atau sertifikat sudah selesai diterbitkan.</p>
        </details>
        <details class="faq-item">
            <summary>Siapa yang harus dihubungi jika ada kendala?</summary>
            <p>Jika mengalami kendala saat penerbitan dokumen sertifikat, silakan hubungi Superadmin.</p>
        </details>
    </div>
</div>
