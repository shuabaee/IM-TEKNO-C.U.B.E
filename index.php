<?php
require_once 'connect.php';
$pageTitle = 'Home';
require_once ROOT_PATH . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-inner">
        <div>
            <div class="eyebrow">CIT-U Laboratory Asset Portal</div>
            <h1>Streamlined laboratory access for <span>Teknoys</span>.</h1>
            <p>TEKNO C.U.B.E. brings borrowing, reservations, inventory monitoring, return inspections, and settlement tracking into one organized campus portal.</p>
            <div class="hero-chips">
                <span class="hero-chip">Student Borrowing</span>
                <span class="hero-chip">Instructor Reservations</span>
                <span class="hero-chip">Laboratory Staff Inspection</span>
            </div>
        </div>
        <div class="hero-panel hero-panel-rich">
            <div class="hero-visual-card">
                <img class="hero-visual-image" src="<?= url('images/hero-lab-illustration.svg') ?>" alt="TEKNO C.U.B.E. laboratory portal illustration">
            </div>
            <div class="showcase-grid">
                <div class="showcase-item">
                    <span class="showcase-step">01</span>
                    <div>
                        <strong>Borrow</strong>
                        <p>Students can view availability and request items online.</p>
                    </div>
                </div>
                <div class="showcase-item">
                    <span class="showcase-step">02</span>
                    <div>
                        <strong>Reserve</strong>
                        <p>Instructors can reserve batch quantities by department.</p>
                    </div>
                </div>
                <div class="showcase-item">
                    <span class="showcase-step">03</span>
                    <div>
                        <strong>Inspect</strong>
                        <p>Laboratory staff can confirm returns and manage settlement.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container section">
    <div class="section-shell">
        <div class="section-title section-title-tight">
            <div>
                <div class="section-kicker">Main Modules</div>
                <h2>Portal Access</h2>
                <p>Each user role has a focused workflow in the TEKNO C.U.B.E. portal.</p>
            </div>
        </div>
        <div class="grid-3 feature-grid">
            <article class="card feature-card accent-gold">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2-8 4.5V20h16v-1.5C20 16 16.4 14 12 14Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="feature-label">Student Module</div>
                <h3>Student Access</h3>
                <p>Browse available items, submit borrow requests, and monitor return and liability status.</p>
            </article>
            <article class="card feature-card accent-maroon">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3v3M16 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="feature-label">Instructor Module</div>
                <h3>Instructor Reservation</h3>
                <p>Create reservation batches, assign quantities, request returns, and review inspection feedback.</p>
            </article>
            <article class="card feature-card accent-dark">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v4H3Zm2 4h4v6H5Zm10 0h4v6h-4Zm-5 0h4v6h-4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="feature-label">Admin Module</div>
                <h3>Laboratory Staff</h3>
                <p>Manage users, inventory, return inspections, breakage reports, settlements, and account unblocking.</p>
            </article>
        </div>
    </div>
</section>

<section id="rules" class="container section section-tight">
    <div class="section-shell">
        <div class="section-title section-title-tight">
            <div>
                <div class="section-kicker">Operational Flow</div>
                <h2>Campus Workflow</h2>
                <p>Designed for a smooth laboratory asset process from borrowing up to settlement.</p>
            </div>
        </div>
        <div class="workflow-grid">
            <article class="workflow-card">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M13 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h3>Borrow and Reserve</h3>
                <p>Students can borrow available items, while instructors can reserve batch quantities for laboratory activities.</p>
            </article>
            <article class="workflow-card">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M7 16l3-3 2 2 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 5h12v6H6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h3>Inspect and Report</h3>
                <p>Returned items are inspected by laboratory staff, with comments recorded and damaged returns converted into breakage reports when applicable.</p>
            </article>
            <article class="workflow-card">
                <div class="card-icon svg-icon-box">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 10V7a4 4 0 1 1 8 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10h12v10H6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.5 15a1.5 1.5 0 0 0 3 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h3>Settle and Unblock</h3>
                <p>Once settlement is marked as paid, the system can clear the student liability status and restore borrowing access.</p>
            </article>
        </div>
    </div>
</section>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
