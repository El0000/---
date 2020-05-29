<?php

require_once('types/Gamer.php');
require_once('types/Cell.php');

class PiXO {

    const SIDE_X = 'X';
    const SIDE_O = 'O';

    function __construct($db, $aiId) {
        $this->db = $db;
        $this->aiId = $aiId;
    }

    private function createField() {
        return array(
            array(
                new Cell(1, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(2, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(3, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(4, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(5, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(6, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(7, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(8, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(9, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            )
        );
    }    

    public function updateGame($userId,$game) {
        return $this->db->updateGame($userId,$game);
    }

    public function createGame($partyId) {
        // создать пустое игровое поле
        $field = serialize($this->createField()); // перевести его в строку
        $hash = md5($field); // получить хеш-сумму от этого поля
        $this->db->updateGame($partyId, $field, $hash); // записать в БД
    }

    public function getGame($party, $hash) {
        if ($party->hash != $hash) { 
            return array(
                'field' => unserialize($party->game),
                'hash' => $party->hash,
                'turn' => $party->turn,
                'gamers' => array(
                    'gamer1' => $this->db->getGamerById($party->user1_id),
                    'gamer2' => $this->db->getGamerById($party->user2_id)
                )
            );
        }
        return false;
    }

    // вернуть окончание игры
    public function getEndGame($party) {
        if ($party->winner_id) {
            $loserId = ($party->winner_id === $party->user1_id) ? $party->user2_id : $party->user1_id;
            return array(
                'endGame' => true,
                'status' => 'Победа!',
                'winner' => $this->db->getGamerById($party->winner_id)->login,
                'loser' => $this->db->getGamerById($party->loserId)->login
            );
        }
        return array(
            'endGame' => true,
            'status' => 'Ничья!'
        );
    }

    private function checkEmptyCells($cell) {
        for($i = 0; $i <= 2; $i++)
            for($j = 0; $j <= 2; $j++)
                if(!$cell[$i][$j]) return true;
        return false;
    }

    private function isSameValues($cell1, $cell2, $cell3) {
        return !!($cell1 === $cell2 && $cell2 === $cell3 && $cell1 === $cell3);
    }

    private function checkCell($cell, $y, $x, $value) {
        // сравнение значений в столбце
        if ($this->isSameValues($cell->field[$y][0], 
                                $cell->field[$y][1], 
                                $cell->field[$y][2])
        ) {
            return $value;
        }
        // сравнение значений в строке
        if ($this->isSameValues($cell->field[0][$x], 
                                $cell->field[1][$x], 
                                $cell->field[2][$x])
        ) {
            return $value;
        }
        // сравнение диагоналей
        // главная диагональ
        if ($y == $x) {
            if ($this->isSameValues($cell->field[0][0], 
                                    $cell->field[1][1], 
                                    $cell->field[2][2])
            ) {
                return $value;
            }
        }
        // побочная диагональ
        if ($y + $x == 2) {
            if ($this->isSameValues($cell->field[0][2], 
                                    $cell->field[1][1], 
                                    $cell->field[2][0])
            ) { 
                return $value;
            }
        }
        if (!$this->checkEmptyCells($cell->field)) {
            return 'draw';
        }
        return null;
    }

    private function checkGame($feild, $r1, $r2, $result) {
        /*
        if($field[$r1][0]->result == $field[$r1][1]->result == $field[$r1][2]->result) 
            return $result;

        if($field[0][$r2]->result == $field[1][$r2]->result == $field[2][$r2]->result) 
            return $result;
        
        if($r1 == $r2)
            if($field[0][0]->result == $field[1][1]->result == $field[2][2]->result) 
                return $result;
               
        if($r1 + $r2 == 2)
            if($field[0][0]->result == $field[1][1]->result == $field[2][2]->result) 
                return $result;
                */
        return null;
    }

    // игрок сходил как-то
    public function turn($party, $user, $x, $y) {
        if ($user->id === $party->turn) { // проверить, что ход игрока
            $field = unserialize($party->game);
            $r1 = floor($y/3);
            $r2 = floor($x/3);
            $r3 = floor($y - 3 * $r1);
            $r4 = floor($x - 3 * $r2);
            if (!$field[$r1][$r2]->result) { // проверить, что в малый квадрат можно ходить
                // проверить, что в ячейке ещё ничего нет
                if ($field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_X && 
                    $field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_O
                ) {
                    // совершить ход
                    $value = ($party->user1_id === $user->id) ? $this::SIDE_X : $this::SIDE_O;
                    $field[$r1][$r2]->field[$r3][$r4] = $value;
                    // проверить на победу в ячейке
                    $field[$r1][$r2]->result = $this->checkCell($field[$r1][$r2], $r3, $r4, $value);
                    // проверить на победу в игре
                    //...
                    // записать данные в БД
                    $fieldStr = serialize($field); // перевести его в строку
                    $hash = md5($fieldStr); // получить хеш-сумму от этого поля
                    $this->db->updateGame($party->id, $fieldStr, $hash); // записать в БД
                    // поменять turn партии
                    ($user->id == $party->user1_id) ? $this->db->updateTurn($party->id, $party->user2_id) : 
                                                      $this->db->updateTurn($party->id, $party->user1_id);

                    // Если игра ведется с ИИ
                    $party = $this->db->getPartyByUserId($user->id, 'game');
                    // Проверка на ИИ    
                    if ($party->turn == $this->aiId) { 
                        $field = unserialize($party->game);
                        // Получаем координаты, куда сходил ИИ
                        $сoordinates = $this->aiTurn($field);
                        $field[$сoordinates->r1][$сoordinates->r2]->field[$сoordinates->r3][$сoordinates->r4] = $this::SIDE_O;
                        $field[$сoordinates->r1][$сoordinates->r2]->result = $this->checkCell($field[$сoordinates->r1][$сoordinates->r2], $сoordinates->r3, $сoordinates->r4, $this::SIDE_O);
                        $fieldStr = serialize($field);
                        $hash = md5($fieldStr); 
                        $this->db->updateGame($party->id, $fieldStr, $hash);
                        $this->db->updateTurn($party->id, $party->user1_id);
                    }
                    return true;
                }
            }

        }
        return false;
    }

    // Полный ход ИИ
    public function aiTurn($fields) {
        $aiCoordinates = (object) ['r1' => null, 'r2' => null, 'r3' => null, 'r4' => null];
        for ($i = 0; $i < count($fields); $i++) {
            for ($j = 0; $j < count($fields[$i]); $j++) {
                $cell = $fields[$i][$j];
                if ($cell->result) {

                } else {
                    $copyField = $cell->field;
                    $coordinates = $this->firstAiDo($copyField); // Самый первый ход
                    if ($coordinates) {
                        $aiCoordinates = (object) ['r1' => $i, 'r2' => $j, 'r3' => $coordinates->r3, 'r4' => $coordinates->r4];
                        return $aiCoordinates;
                    }
                    $copyCell = clone $cell;
                    $coordinates = $this->aiDoOneStepForward($copyCell); // Ход на один шаг вперед
                    if ($coordinates) {
                        $aiCoordinates = (object) ['r1' => $i, 'r2' => $j, 'r3' => $coordinates->r3, 'r4' => $coordinates->r4];
                        return $aiCoordinates;
                    }
                    $copyCell = clone $cell;
                    $coordinates = $this->aiDoTwoStepForward($copyCell); // // Ход ИИ на два шага вперед
                    if ($coordinates) {
                        $aiCoordinates = (object) ['r1' => $i, 'r2' => $j, 'r3' => $coordinates->r3, 'r4' => $coordinates->r4];
                        return $aiCoordinates;
                    }
                    return null;
                }
            }
        }
    }

    // Первый ход ИИ (ходил ли ИИ в этом поле)
    public function firstAiDo($field) {
        for ($i = 0; $i < count($field); $i++) {
            for ($j = 0; $j < count($field[$i]); $j++) {
                if ($field[$i][$j] === $this::SIDE_O) {
                    return null;
                }
            }
        }
        $flagRandom = false;
        $coordinates = $this->getRandomCoordinates();
        while (!$flagRandom) {
            if ($field[$coordinates->r3][$coordinates->r4] === $this::SIDE_X) {
                $coordinates = $this->getRandomCoordinates();
            } else {
                $flagRandom = true;
            }
        }
        return $coordinates;
    }

    // Создает случайные координаты
    public function getRandomCoordinates() {
        $randR3 = rand(0, 2);
        $randR4 = rand(0, 2);
        return (object) ['r3' => $randR3, 'r4' => $randR4 ];
    }

    // Ход ИИ на один шаг вперед
    public function aiDoOneStepForward($cell) {
        for ($i = 0; $i < count($cell->field); $i++) {
            for ($j = 0; $j < count($cell->field[$i]); $j++) {
                $copyCell = clone $cell;
                if ($copyCell->field[$i][$j] === 0) {
                    $copyCell->field[$i][$j] = $this::SIDE_O;
                    $result = $this->checkCell($copyCell, $i, $j, $this::SIDE_O);
                    if ($result) {
                        return (object) ['r3' => $i, 'r4' => $j];
                    }
                }
            }
        }
    }
    // Ход ИИ на два шага вперед
    public function aiDoTwoStepForward($cell) {
        for ($i1 = 0; $i1 < count($cell->field); $i1++) {
            for ($j1 = 0; $j1 < count($cell->field[$i1]); $j1++) {
                $copyCell = clone $cell;
                if ($copyCell->field[$i1][$j1] === 0) {
                    $copyCell->field[$i1][$j1] = $this::SIDE_O;
                    for ($i2 = 0; $i2 < count($cell->field); $i2++) {
                        for ($j2 = 0; $j2 < count($cell->field[$i2]); $j2++) {
                            $copyCopyCell = clone $copyCell;
                            if ($copyCopyCell->field[$i2][$j2] === 0) {
                                $copyCopyCell->field[$i2][$j2] = $this::SIDE_O;
                                $result = $this->checkCell($copyCopyCell, $i2, $j2, $this::SIDE_O);
                                if ($result) {
                                    return (object) ['r3' => $i1, 'r4' => $j1];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
