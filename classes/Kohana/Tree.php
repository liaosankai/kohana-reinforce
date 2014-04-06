<?php

defined('SYSPATH') or die('No direct script access.');

class Kohana_Tree {

    /**
     * 將一個透過 Tree::build() 節點樹扁平化
     *
     * @param array $tree 一個透過 Tree::build() 建立的樹陣列
     * @param array $flat 用來收集節點的陣列
     * @return array
     */
    public static function flat(array $tree, &$flat = array())
    {
        foreach ($tree as $node) {
            $flat[] = $node;
            self::flat($node["children"], $flat);
        }
        return $flat;
    }

    /**
     * 遞迴建節點樹
     *
     * array(
     *   array(
     *     'id' => 1,
     *     'name' => '節點名稱',
     *     'parent_id' => 9,
     *     'rank' => 0
     *   ),
     *   array(
     *     'id' => 2,
     *     'name' => '節點名稱',
     *     'parent_id' => 10,
     *     'rank' => 0
     *   ),
     * );
     *
     * @param array $elements 一個元素有 id, parent_id 的扁平陣列
     * @param int $parent_id
     * @param int $level
     * @param string $parent_name
     * @param array $ancestor_name
     * @return array
     */
    public static function build(array $elements, $parent_id = 0, $level = -1, $parent_name = '', $ancestor_name = array())
    {
        $branch = array();

        foreach ($elements as $node) {
            // 初始化擴充資料
            $node["level"] = -1;  // 記錄節點層級
            $node["parent_name"] = ""; // 記錄父層名稱
            $node["ancestor_name"] = array(); // 記錄祖先層名稱
            $node["children_ids"] = array(); // 記錄子節點 ids
            $node["posterity_ids"] = array(); // 記錄子孫節點 ids
            $node["children"] = array();  // 記錄子節點
            // 記錄節點層級
            $node["level"] = $level + 1;
            // 記錄父層名稱
            $node["parent_name"] = $parent_name;
            // 追加祖先層名稱
            array_push($ancestor_name, $parent_name);
            $node["ancestor_name"] = array_unique(array_filter($ancestor_name));

            if ($node["parent_id"] == $parent_id) {
                $children = self::build($elements, $node["id"], $node["level"], $node["name"], $node["ancestor_name"]);
                if ($children) {
                    usort($children, function($a, $b) {
                        return $a["rank"] - $b["rank"];
                    });
                    $node['children'] = $children;
                    foreach ($children as $child) {
                        // 記錄子節點 id
                        $node["children_ids"][] = $child["id"];
                        // 記錄子孫節點 id
                        $node["posterity_ids"][] = $child["id"];
                        // 合併子孫節點 id
                        $node["posterity_ids"] = array_merge($node["posterity_ids"], $child["posterity_ids"]);
                    }
                }
                $branch[] = $node;
            }
        }
        return $branch;
    }

}
