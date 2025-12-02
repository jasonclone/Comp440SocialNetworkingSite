<html>
<style>
	table, th, td {
		border: 1px solid black;
	}

	html, body {
		margin: 0;
		padding: 12px;
		height: 100%;
		width: 100%;
	}
</style>

<!--Ajax live search -->

<?php
	include("db_connect.php");

	if(isset($_POST['input'])){
		$input = trim($_POST['input']);
		$stmt = $UserDBConnect->prepare("SELECT * FROM Blogs WHERE tags LIKE ? ORDER BY username");
		$searchTerm = "%{$_POST['input']}%";
		$stmt->bind_param("s", $searchTerm);
		$stmt->execute();
		$result = $stmt->get_result();

		//if(!empty($input)){
			//$query = "SELECT * FROM Blogs WHERE tags LIKE '%{$input}%' ORDER BY username";
			
		//} else {
			//echo "error";
		//}

		//$result = mysqli_query($UserDBConnect, $query)

		//if(mysqli_num_rows($result) > 0){
		?>

			<table class = "table table-bordered table-striped mt-4">
				<thead>
					<tr>
						<th style = "width:10%"> Subject </th>
						<th style = "width:10%"> Description </th>
						<th style = "width:10%"> Tags </th>
						<th style = "width:10%"> Username </th>
						<th style = "width:10%"> Created At </th>
					</tr>
				</thead>
				<tbody>
					<?php 
					//while($row = mysqli_fetch_assoc($result)){
					if($result && $result->num_rows > 0){
						while($row = $result->fetch_assoc()){
					
					?>
						<tr>
							<td> <?php echo htmlspecialchars($row['subject']); ?></td>
							<td> <?php echo htmlspecialchars($row['description']); ?></td>
							<td> <?php echo htmlspecialchars($row['tags']); ?></td>
							<td> <?php echo htmlspecialchars($row['username']); ?></td>
							<td> <?php echo htmlspecialchars($row['created_at']); ?></td>
						</tr>
					<?php }
					} else {?>
						<tr>
							<td colspan = "5" style = "text-align:center; color:red;"> No Data Found</td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		<?php			
		$stmt->close();
	}
?>
</html>