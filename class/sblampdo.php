<?php

class SblamPDO extends PDO
{
    function prepareExecute($q,array $data)
    {
        $statement = $this->prepare($q);
		if (!$statement) throw new PDOException(implode(',',$this->errorInfo().'// '.$q));
		if (!$statement->execute($data)) throw new PDOException(implode(',',$statement->errorInfo().'// '.$q));
		return $statement;
    }
}
