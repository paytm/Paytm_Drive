//
//  TransactionDetailViewController.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 30/09/24.
//

import UIKit

class TransactionDetailViewController: UIViewController {
    
    //MARK: IB Outlets
    
    @IBOutlet weak var statusLabel: UILabel!
    @IBOutlet weak var merchantName: UILabel!
    @IBOutlet weak var amountLabel: UILabel!
    @IBOutlet weak var orderIdLabel: UILabel!
    @IBOutlet weak var txnDateLabel: UILabel!
    @IBOutlet weak var failureReasonLbl: UILabel! {
        didSet {
            failureReasonLbl.isHidden = true
            failureReasonLbl.text = nil
        }
    }
    @IBOutlet weak var txnStatusImgView: UIImageView!
    
    //MARK: Variables
    
    var orderDetailsDict = [String:Any]()

    //MARK: Lifecycle methods
    
    override func viewDidLoad() {
        super.viewDidLoad()
        configureLabels()
    }
    
    //MARK: UI Functions
    
    func configureLabels() {
        if let orderId = orderDetailsDict["orderId"] as? String, let amount = orderDetailsDict["amount"] as? String, let businessName = orderDetailsDict["businessName"] as? String, let txnDate = orderDetailsDict["txnDate"] as? String{
            orderIdLabel.text = "Order ID: \(orderId)"
            amountLabel.text = "₹ \(amount)"
            txnDateLabel.text = txnDate
            merchantName.text = businessName
            if let status = orderDetailsDict["status"] as? String, status == "TXN_SUCCESS" {
                statusLabel.text = "Paid Successfully to"
                txnStatusImgView.image = UIImage(named: "SuccessTxn")
            } else {
                statusLabel.text = "Payment was unsuccessful for"
                txnStatusImgView.image = UIImage(named: "FailureTxn")
                if businessName.isEmpty {
                    merchantName.isHidden = true
                    merchantName.text = nil
                }
                if let message = orderDetailsDict["message"] as? String {
                    failureReasonLbl.isHidden = false
                    failureReasonLbl.text = "Reason: \(message)"
                }
            }
        } else {
            CommonUtility.showBasicAlert(on: self, title: "Data Parsing Error", message: "Data couldn't be processed")
        }
    }
    
    //MARK: IBAction Methods

    @IBAction func reScanBtn(_ sender: Any) {
        
        guard let viewControllers = self.navigationController?.viewControllers else { return }
        
        for viewController in viewControllers {
            if let targetVC = viewController as? QRScannerViewController {
                self.navigationController?.popToViewController(targetVC, animated: true)
                return
            }
        }
    }
}
