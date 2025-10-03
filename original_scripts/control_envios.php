<?php
defined('_JEXEC') or die;

// Get the Joomla database object
$db = JFactory::getDbo();

// Prepare the query to fetch rows where tipo_de_campo = 'mensajero'
$query = $db->getQuery(true);
$query->select([$db->quoteName('valor'), $db->quoteName('usuario')])
      ->from($db->quoteName('ordenes_info')) // Adjust the table name with your Joomla prefix
      ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('mensajero'));

$db->setQuery($query);
$results = $db->loadAssocList();

// Check if records exist
if (!$results) {
    echo "<p>No records found.</p>";
    return;
}
?>

<div class="custom-form">
    <form action="<?php echo JRoute::_('index.php'); ?>" method="POST">
        <label for="dropdown">Seleccione Mensajero:</label>
        <select name="dropdown" id="dropdown" required>
            <option value="">-- Selecccion --</option>
            <?php foreach ($results as $row): ?>
                <option value="<?php echo htmlspecialchars($row['usuario']); ?>">
                    <?php echo htmlspecialchars($row['valor']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Submit</button>
        <!-- Add a token for Joomla form security -->
        <?php echo JHtml::_('form.token'); ?>
    </form>
</div>
