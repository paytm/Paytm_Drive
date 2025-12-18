//
//  DropDownTableViewCell.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 28/02/24.
//

import UIKit

class DropDownTableViewCell: UITableViewCell {

    @IBOutlet weak var reasonLbl: UILabel!
    
    override func awakeFromNib() {
        super.awakeFromNib()
    }

    override func setSelected(_ selected: Bool, animated: Bool) {
        super.setSelected(selected, animated: animated)
    }
    
    func configureCell(label: String) {
        self.reasonLbl.text = label
    }
    
}
