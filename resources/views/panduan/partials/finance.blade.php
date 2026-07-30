<div class="guide-header">
    <div class="guide-header-title">
        <span class="guide-icon">💳</span>
        <div>
            <h2>Panduan Finance</h2>
            <p>Pelajari langkah utama untuk mengelola tagihan invoice, memeriksa pembayaran, dan memverifikasi bukti transfer Klien.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Mulai dari Sini</h3>
    <div class="step-cards-grid">
        <div class="step-card">
            <span class="step-badge">Langkah 1</span>
            <h4>Membuka Order Masuk</h4>
            <p>Buka daftar permohonan yang telah disetujui administrasi dan siap untuk proses tagihan.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 2</span>
            <h4>Membuat atau Mengunggah Invoice</h4>
            <p>Terbitkan invoice resmi dengan rincian biaya sertifikasi agar tagihan dapat diakses oleh Klien.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 3</span>
            <h4>Memeriksa Pembayaran</h4>
            <p>Pantau status konfirmasi dan periksa notifikasi masuk mengenai bukti pembayaran dari Klien.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 4</span>
            <h4>Memverifikasi Bukti Pembayaran</h4>
            <p>Periksa kesesuaian nominal dana yang masuk dan validitas lampiran transfer yang diunggah Klien.</p>
        </div>
        <div class="step-card">
            <span class="step-badge">Langkah 5</span>
            <h4>Menyelesaikan Proses Pembayaran</h4>
            <p>Konfirmasi pembayaran selesai agar order dapat dijadwalkan untuk proses audit berikutnya.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Tindakan Cepat</h3>
    <div class="quick-actions-grid">
        <a href="{{ route('finance.index') }}" class="quick-action-btn">
            <strong>Buka Invoice dan Pembayaran</strong>
            <span>Kelola penerbitan tagihan dan verifikasi pembayaran</span>
        </a>
    </div>
</div>

<div class="guide-section">
    <h3>Kapan Saya Perlu Bertindak?</h3>
    <div class="status-action-list">
        <div class="status-action-item">
            <span class="status-label badge-success">Menunggu Invoice</span>
            <p>Buat dan kirimkan tagihan resmi kepada Klien yang permohonannya telah lolos administrasi.</p>
        </div>
        <div class="status-action-item">
            <span class="status-label badge-success">Verifikasi Pembayaran</span>
            <p>Periksa bukti transfer pembayaran dari Klien dan konfirmasi pembayaran agar alur audit terbuka.</p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>Bantuan Cepat</h3>
    <div class="faq-accordion">
        <details class="faq-item">
            <summary>Mengapa tombol tertentu tidak muncul?</summary>
            <p>Tombol penerbitan invoice atau verifikasi hanya muncul jika permohonan berada pada tahap keuangan yang sesuai.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa data tidak dapat diedit?</summary>
            <p>Invoice yang telah diterbitkan dan dikonfirmasi lunas dikunci untuk menjaga konsistensi pencatatan keuangan.</p>
        </details>
        <details class="faq-item">
            <summary>Mengapa order tidak muncul?</summary>
            <p>Permohonan baru akan masuk ke daftar Finance setelah lolos tahap verifikasi administrasi oleh Admin Permohonan.</p>
        </details>
        <details class="faq-item">
            <summary>Apa arti status yang sedang tampil?</summary>
            <p>Status menampilkan tahapan tagihan, apakah invoice belum dibuat, menunggu pembayaran dari Klien, atau pembayaran selesai.</p>
        </details>
        <details class="faq-item">
            <summary>Siapa yang harus dihubungi jika ada kendala?</summary>
            <p>Jika mengalami masalah saat memverifikasi atau mencetak invoice, silakan hubungi Superadmin.</p>
        </details>
    </div>
</div>
