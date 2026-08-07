```php
<?php if ($ticketId): ?>
  <form class="form">
    <p class="title">Request Submitted</p>
    <p class="message">
      Keep this ticket ID safe — you'll need it to track your request below.
    </p>

    <p class="mono" style="font-size:22px;color:var(--copper-lt);text-align:center;margin:8px 0">
      <?= h($ticketId) ?>
    </p>
  </form>

<?php else: ?>

  <form class="form" method="POST">
    <p class="title">Join EESA</p>

    <p class="message">
      Submit your details — an admin will review your request and email your login credentials once approved.
    </p>

    <?php if ($err): ?>
      <div class="alert alert-err"><?= h($err) ?></div>
    <?php endif; ?>

    <?= csrf_field() ?>

    <label>
      <input
        class="input"
        type="text"
        name="full_name"
        placeholder=" "
        required
        value="<?= h($_POST['full_name'] ?? '') ?>"
      >
      <span>Full Name</span>
    </label>

    <label>
      <input
        class="input"
        type="email"
        name="email"
        placeholder=" "
        required
        value="<?= h($_POST['email'] ?? '') ?>"
      >
      <span>Email</span>
    </label>

    <div class="flex">

      <!-- Branch -->
      <label style="flex:1">
        <select class="input" name="branch" required>
          <option value="" disabled <?= empty($_POST['branch']) ? 'selected' : '' ?>>
            Select Branch
          </option>

          <option value="Electrical Engineering"
            <?= ($_POST['branch'] ?? '') === 'Electrical Engineering' ? 'selected' : '' ?>>
            Electrical Engineering
          </option>

          <option value="Computer Science & Engineering"
            <?= ($_POST['branch'] ?? '') === 'Computer Science & Engineering' ? 'selected' : '' ?>>
            Computer Science & Engineering
          </option>

          <option value="Information Technology"
            <?= ($_POST['branch'] ?? '') === 'Information Technology' ? 'selected' : '' ?>>
            Information Technology
          </option>

          <option value="Electronics & Telecommunication"
            <?= ($_POST['branch'] ?? '') === 'Electronics & Telecommunication' ? 'selected' : '' ?>>
            Electronics & Telecommunication
          </option>

          <option value="Mechanical Engineering"
            <?= ($_POST['branch'] ?? '') === 'Mechanical Engineering' ? 'selected' : '' ?>>
            Mechanical Engineering
          </option>

          <option value="Civil Engineering"
            <?= ($_POST['branch'] ?? '') === 'Civil Engineering' ? 'selected' : '' ?>>
            Civil Engineering
          </option>

          <option value="Chemical Engineering"
            <?= ($_POST['branch'] ?? '') === 'Chemical Engineering' ? 'selected' : '' ?>>
            Chemical Engineering
          </option>

          <option value="Production Engineering"
            <?= ($_POST['branch'] ?? '') === 'Production Engineering' ? 'selected' : '' ?>>
            Production Engineering
          </option>

          <option value="Textile Technology"
            <?= ($_POST['branch'] ?? '') === 'Textile Technology' ? 'selected' : '' ?>>
            Textile Technology
          </option>

          <option value="Instrumentation Engineering"
            <?= ($_POST['branch'] ?? '') === 'Instrumentation Engineering' ? 'selected' : '' ?>>
            Instrumentation Engineering
          </option>
        </select>

        <span>Branch</span>
      </label>

      <!-- Year -->
      <label style="flex:1">
        <select class="input" name="year_of_study" required>
          <option value="" disabled <?= empty($_POST['year_of_study']) ? 'selected' : '' ?>>
            Select Year
          </option>

          <option value="1st Year"
            <?= ($_POST['year_of_study'] ?? '') === '1st Year' ? 'selected' : '' ?>>
            1st Year
          </option>

          <option value="2nd Year"
            <?= ($_POST['year_of_study'] ?? '') === '2nd Year' ? 'selected' : '' ?>>
            2nd Year
          </option>

          <option value="3rd Year"
            <?= ($_POST['year_of_study'] ?? '') === '3rd Year' ? 'selected' : '' ?>>
            3rd Year
          </option>

          <option value="Final Year"
            <?= ($_POST['year_of_study'] ?? '') === 'Final Year' ? 'selected' : '' ?>>
            Final Year
          </option>
        </select>

        <span>Year</span>
      </label>

    </div>

    <button class="submit" type="submit" name="submit_join">
      Submit Request
    </button>
  </form>

<?php endif; ?>


<!-- Track Request -->
<form class="form" method="GET">

  <p class="title">Track Request</p>

  <p class="message">
    Already applied? Enter your ticket ID to check its status.
  </p>

  <?php if ($trackErr): ?>
    <div class="alert alert-err"><?= h($trackErr) ?></div>
  <?php endif; ?>

  <label>
    <input
      class="input"
      type="text"
      name="ticket_id"
      placeholder=" "
      required
      value="<?= h($_GET['ticket_id'] ?? '') ?>"
    >
    <span>Ticket ID</span>
  </label>

  <button class="submit" type="submit">
    Check Status
  </button>

  <?php if ($trackResult): ?>

    <?php [$label, $badgeClass] = join_status_label($trackResult['status']); ?>

    <div
      style="
        margin-top:6px;
        padding:14px;
        border-radius:10px;
        background:#2b2b2b;
        border:1px solid rgba(105,105,105,0.4)
      "
    >

      <p style="margin:0 0 4px;font-weight:600">
        <?= h($trackResult['full_name']) ?>
      </p>

      <p
        class="muted mono"
        style="margin:0 0 4px;font-size:12px"
      >
        Ticket: <?= h($trackResult['ticket_id']) ?>
      </p>

      <span class="badge <?= h($badgeClass) ?>">
        <?= h($label) ?>
      </span>

    </div>

  <?php endif; ?>

</form>
```
